<?php

namespace App\Http\Controllers;

use App\Concerns\ScopesByUser;
use App\Models\Cuota;
use App\Models\Gasto;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Services\CajaService;
use Illuminate\Http\Request;

class ResumenController extends Controller
{
    use ScopesByUser;

    public function index(Request $request, CajaService $caja)
    {
        $desde = $request->query('desde', today()->startOfMonth()->toDateString());
        $hasta = $request->query('hasta', today()->toDateString());
        $ids = $this->userIds();

        $porDia = Pago::whereIn('user_id', $ids)->whereBetween('fecha', [$desde, $hasta])
            ->selectRaw('fecha, SUM(valor) as total, COUNT(*) as pagos')
            ->groupBy('fecha')->orderByDesc('fecha')->get();

        $cobradoPeriodo = (float) $porDia->sum('total');
        $gastosPeriodo = (float) Gasto::whereIn('user_id', $ids)->whereBetween('fecha', [$desde, $hasta])->sum('monto');

        $activos = Prestamo::whereIn('user_id', $ids)->where('estado', 'activo')->select('id');
        $enLaCalle = (float) Cuota::whereIn('prestamo_id', $activos)->sum(\DB::raw('valor - pagado'));
        $atrasado = (float) Cuota::whereIn('prestamo_id', $activos)->where('estado', 'pendiente')
            ->where('fecha', '<', today()->toDateString())->sum(\DB::raw('valor - pagado'));

        $cobradoresRows = $this->usuariosVisibles()->map(function ($u) use ($caja, $desde, $hasta) {
            $cobradoPer = (float) Pago::where('user_id', $u->id)->whereBetween('fecha', [$desde, $hasta])->sum('valor');

            return ['usuario' => $u, 'cobrado_periodo' => $cobradoPer] + $caja->desglose($u->id);
        });

        return view('resumen.index', compact(
            'desde', 'hasta', 'porDia', 'cobradoPeriodo', 'gastosPeriodo', 'enLaCalle', 'atrasado', 'cobradoresRows'
        ));
    }
}
