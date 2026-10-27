<?php

namespace App\Http\Controllers;

use App\Concerns\ScopesByUser;
use App\Models\Cliente;
use App\Models\Cuota;
use App\Models\Prestamo;
use App\Services\AuditoriaService;
use App\Services\CajaService;
use App\Services\CalificacionService;
use App\Services\PrestamoService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PrestamoController extends Controller
{
    use ScopesByUser;

    public function index(Request $request, CalificacionService $calificacion)
    {
        $estado = $request->query('estado', 'activo');
        $q = trim((string) $request->query('q'));

        // Los anulados están borrados lógicamente: se listan aparte
        $query = $estado === 'anulado' ? Prestamo::onlyTrashed() : Prestamo::query();

        $prestamos = $query->with('cliente', 'user')->conTotales()
            ->whereIn('user_id', $this->userIds())
            ->when(! in_array($estado, ['todos', 'anulado'], true), fn ($qq) => $qq->where('estado', $estado))
            ->when($q, fn ($qq) => $qq->whereHas('cliente', fn ($c) => $c
                ->where('nombres', 'like', "%$q%")->orWhere('apellidos', 'like', "%$q%")))
            ->latest('id')->paginate(15)->withQueryString();

        $cals = $calificacion->paraClientes($prestamos->pluck('cliente_id')->all());

        return view('prestamos.index', compact('prestamos', 'estado', 'q', 'cals'));
    }

    public function create(CajaService $caja, CalificacionService $calificacion)
    {
        $usuarios = $this->usuariosVisibles();
        $clientes = Cliente::whereIn('user_id', $this->userIds())->where('activo', true)
            ->orderBy('nombres')->get(['id', 'user_id', 'nombres', 'apellidos']);

        $cajas = [];
        if (config('cobros.usar_base')) {
            foreach ($usuarios as $u) {
                $cajas[$u->id] = $caja->caja($u->id);
            }
        }

        return view('prestamos.create', [
            'usuarios' => $usuarios,
            'clientes' => $clientes,
            'cajas' => $cajas,
            'cals' => $calificacion->paraClientes($clientes->pluck('id')->all()),
            'bloquear' => config('cobros.bloquear_sin_base'),
            'usarBase' => config('cobros.usar_base'),
            'festivos' => \App\Support\FestivosColombia::rango(today()->year, today()->year + 3),
            'clienteId' => request('cliente_id'),
        ]);
    }

    public function store(Request $request, PrestamoService $service)
    {
        $data = $request->validate([
            'cliente_id' => 'required|integer|exists:clientes,id',
            'valor_cuota' => 'required|numeric|min:1|max:999999999',
            'num_cuotas' => 'required|integer|min:1|max:520',
            'frecuencia' => ['required', Rule::in(Prestamo::FRECUENCIAS)],
            'saltar_domingos' => 'nullable|boolean',
            'saltar_festivos' => 'nullable|boolean',
            'fecha_inicio' => 'required|date',
        ]);

        $cliente = Cliente::findOrFail($data['cliente_id']);
        $this->autorizar($cliente);

        // El préstamo queda a nombre del cobrador dueño del cliente
        $data['user_id'] = $cliente->user_id;
        $data['saltar_domingos'] = $request->boolean('saltar_domingos');
        $data['saltar_festivos'] = $request->boolean('saltar_festivos');

        $prestamo = $service->crear($data);

        return redirect()->route('prestamos.show', $prestamo)->with('ok', 'Préstamo creado con '.$prestamo->num_cuotas.' cuotas.');
    }

    public function show(int $id, CalificacionService $calificacion)
    {
        $prestamo = Prestamo::withTrashed()->with('cliente', 'user', 'cuotas', 'pagos.user')->conTotales()->findOrFail($id);
        $this->autorizar($prestamo);

        $cal = $calificacion->paraCliente($prestamo->cliente_id);
        $auditoria = \App\Models\Auditoria::with('user')->where('prestamo_id', $prestamo->id)->latest('id')->limit(100)->get();

        return view('prestamos.show', compact('prestamo', 'cal', 'auditoria'));
    }

    /** Renueva un préstamo (pagado o con cuotas pendientes): descuenta el saldo y se entrega la diferencia. */
    public function renovar(Request $request, int $id, PrestamoService $service)
    {
        $anterior = Prestamo::findOrFail($id);
        $this->autorizar($anterior);

        abort_unless(in_array($anterior->estado, ['activo', 'pagado'], true), 422, 'Este préstamo no se puede renovar.');

        $data = $request->validate([
            'monto' => 'required|numeric|min:1|max:9999999999',
            'valor_cuota' => 'required|numeric|min:1|max:999999999',
            'num_cuotas' => 'required|integer|min:1|max:520',
            'fecha_inicio' => 'required|date',
        ]);

        $r = $service->renovar($anterior, $data, $request->user()->id);

        $msg = 'Préstamo renovado: '.$r['prestamo']->num_cuotas.' cuotas de $'.number_format($r['prestamo']->valor_cuota, 0, ',', '.').'.';
        if ($r['saldo'] > 0) {
            $msg .= ' Saldo anterior descontado: $'.number_format($r['saldo'], 0, ',', '.').'. Entregar al cliente: $'.number_format($r['entregar'], 0, ',', '.').'.';
        }

        return redirect()->route('prestamos.show', $r['prestamo']->id)->with('ok', $msg);
    }

    public function anular(Request $request, int $id, PrestamoService $service)
    {
        $motivo = $request->validate(['motivo' => 'required|string|min:5|max:255'])['motivo'];

        $prestamo = Prestamo::findOrFail($id);
        $this->autorizar($prestamo);

        $u = auth()->user();
        abort_unless($u->esGestor() || ! $prestamo->pagos()->exists(), 403, 'Solo admin o empresa pueden anular un préstamo con pagos.');

        $service->anular($prestamo, $motivo);

        return redirect()->route('prestamos.index')->with('ok', 'Préstamo anulado.');
    }

    public function moverCuota(Request $request, Cuota $cuota, AuditoriaService $auditoria)
    {
        $prestamo = Prestamo::findOrFail($cuota->prestamo_id);
        $this->autorizar($prestamo);

        abort_if($cuota->estado !== 'pendiente', 422, 'Solo se mueven cuotas pendientes.');

        $data = $request->validate([
            'fecha' => 'required|date',
            'motivo' => 'required|string|min:5|max:255',
        ]);

        $antes = $cuota->fecha->toDateString();
        $cuota->update(['fecha' => $data['fecha']]);

        if ($antes !== $cuota->fecha->toDateString()) {
            $auditoria->registrar('cuota.fecha_movida',
                'Cuota '.$cuota->numero.': fecha movida del '.\Carbon\Carbon::parse($antes)->format('d/m/Y').' al '.$cuota->fecha->format('d/m/Y'),
                $prestamo->id, 'cuota', $cuota->id, $data['motivo'], ['fecha' => $antes], ['fecha' => $cuota->fecha->toDateString()]);
        }

        return back()->with('ok', 'Fecha de la cuota actualizada.');
    }
}
