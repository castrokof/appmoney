<?php

namespace App\Http\Controllers;

use App\Concerns\ScopesByUser;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Services\CajaService;
use App\Services\CalificacionService;
use Illuminate\Http\Request;

class CobroController extends Controller
{
    use ScopesByUser;

    public function hoy(Request $request, CajaService $caja, CalificacionService $calificacion)
    {
        $q = trim((string) $request->query('q'));
        $hoy = today();

        $prestamos = $this->prestamosConCuotaVencida($q)
            ->sortBy(fn ($p) => [$p->cliente->orden ?? 0, $p->cliente->nombres ?? ''])
            ->values();

        $porCobrar = $prestamos->sum('hoy_calc');
        $cobradoHoy = (float) Pago::whereIn('user_id', $this->userIds())->where('renovacion', false)->whereDate('fecha', $hoy)->sum('valor');
        $cajaTotal = config('cobros.usar_base') ? collect($this->userIds())->sum(fn ($id) => $caja->caja($id)) : null;
        $atrasadoTotal = $prestamos->sum('atrasado_calc');

        $cals = $calificacion->paraClientes($prestamos->pluck('cliente_id')->all());

        return view('cobro.hoy', compact('prestamos', 'porCobrar', 'cobradoHoy', 'cajaTotal', 'atrasadoTotal', 'q', 'cals'));
    }

    public function atrasados(Request $request, CalificacionService $calificacion)
    {
        $q = trim((string) $request->query('q'));

        $prestamos = $this->prestamosConCuotaVencida($q, soloAtrasados: true)
            ->sortByDesc('atrasado_calc')
            ->values();

        $totalAtrasado = $prestamos->sum('atrasado_calc');

        $cals = $calificacion->paraClientes($prestamos->pluck('cliente_id')->all());

        return view('cobro.atrasados', compact('prestamos', 'totalAtrasado', 'q', 'cals'));
    }

    private function prestamosConCuotaVencida(string $q, bool $soloAtrasados = false)
    {
        $hoy = today()->toDateString();
        $op = $soloAtrasados ? '<' : '<=';

        return Prestamo::with('cliente')->conTotales()
            ->whereIn('user_id', $this->userIds())
            ->where('estado', 'activo')
            ->whereHas('cuotas', fn ($c) => $c->where('estado', 'pendiente')->where('fecha', $op, $hoy))
            ->when($q, fn ($query) => $query->whereHas('cliente', fn ($c) => $c
                ->where('nombres', 'like', "%$q%")->orWhere('apellidos', 'like', "%$q%")
                ->orWhere('direccion', 'like', "%$q%")))
            ->get();
    }
}
