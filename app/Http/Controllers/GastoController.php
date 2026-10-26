<?php

namespace App\Http\Controllers;

use App\Concerns\ScopesByUser;
use App\Models\Gasto;
use App\Services\AuditoriaService;
use App\Services\CajaService;
use Illuminate\Http\Request;

class GastoController extends Controller
{
    use ScopesByUser;

    public function index(Request $request, CajaService $caja)
    {
        $mes = $request->query('mes', today()->format('Y-m'));
        $inicio = \Carbon\Carbon::createFromFormat('Y-m', preg_match('/^\d{4}-\d{2}$/', $mes) ? $mes : today()->format('Y-m'))->startOfMonth();

        $gastos = Gasto::with('user')
            ->whereIn('user_id', $this->userIds())
            ->whereBetween('fecha', [$inicio->toDateString(), $inicio->copy()->endOfMonth()->toDateString()])
            ->orderByDesc('fecha')->orderByDesc('id')->get();

        $total = $gastos->sum('monto');
        $cajaActual = config('cobros.usar_base') ? $caja->caja(auth()->id()) : null;
        $mes = $inicio->format('Y-m');

        return view('gastos.index', compact('gastos', 'total', 'mes', 'cajaActual'));
    }

    public function store(Request $request, CajaService $caja)
    {
        $data = $this->validar($request);
        $data['user_id'] = auth()->id();

        $advertencia = null;
        if (config('cobros.usar_base') && $data['monto'] > $caja->caja($data['user_id'])) {
            $advertencia = ' Atención: el gasto deja la caja en negativo.';
        }

        Gasto::create($data);

        return redirect()->route('gastos.index')->with('ok', 'Gasto registrado.'.$advertencia);
    }

    public function update(Request $request, Gasto $gasto, AuditoriaService $auditoria)
    {
        $this->autorizar($gasto);
        $antes = ['monto' => (float) $gasto->monto, 'descripcion' => $gasto->descripcion, 'fecha' => $gasto->fecha->toDateString()];
        $gasto->update($this->validar($request));
        $despues = ['monto' => (float) $gasto->monto, 'descripcion' => $gasto->descripcion, 'fecha' => $gasto->fecha->toDateString()];

        if ($antes !== $despues) {
            $auditoria->registrar('gasto.editado', 'Gasto «'.$gasto->descripcion.'» editado', null, 'gasto', $gasto->id, null, $antes, $despues);
        }

        return redirect()->route('gastos.index')->with('ok', 'Gasto actualizado.');
    }

    public function destroy(Request $request, Gasto $gasto, AuditoriaService $auditoria)
    {
        $this->autorizar($gasto);
        $motivo = $request->validate(['motivo' => 'required|string|min:5|max:255'])['motivo'];

        $auditoria->registrar('gasto.eliminado',
            'Gasto «'.$gasto->descripcion.'» de '.AuditoriaService::dinero($gasto->monto).' eliminado',
            null, 'gasto', $gasto->id, $motivo,
            ['monto' => (float) $gasto->monto, 'descripcion' => $gasto->descripcion, 'fecha' => $gasto->fecha->toDateString()]);
        $gasto->delete();

        return redirect()->route('gastos.index')->with('ok', 'Gasto eliminado.');
    }

    private function validar(Request $request): array
    {
        $data = $request->validate([
            'monto' => 'required|numeric|min:1|max:9999999999',
            'descripcion' => 'required|string|max:150',
            'fecha' => 'nullable|date',
        ]);
        $data['fecha'] = $data['fecha'] ?? today()->toDateString();

        return $data;
    }
}
