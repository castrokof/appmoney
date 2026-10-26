<?php

namespace App\Http\Controllers;

use App\Concerns\ScopesByUser;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Services\PagoService;
use Illuminate\Http\Request;

class PagoController extends Controller
{
    use ScopesByUser;

    public function store(Request $request, Prestamo $prestamo, PagoService $service)
    {
        $this->autorizar($prestamo);

        $data = $request->validate([
            'valor' => 'required|numeric|min:0.01',
            'fecha' => 'nullable|date',
            'nota' => 'nullable|string|max:255',
        ]);

        $pago = $service->aplicar(
            $prestamo,
            (float) $data['valor'],
            $data['fecha'] ?? today()->toDateString(),
            $request->user()->id,
            $data['nota'] ?? null,
        );

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'pago_id' => $pago->id]);
        }

        return back()->with('ok', 'Pago registrado.');
    }

    public function destroy(Request $request, Pago $pago, PagoService $service)
    {
        $motivo = $request->validate(['motivo' => 'required|string|min:5|max:255'])['motivo'];

        $this->autorizar($pago);
        abort_if($pago->renovacion, 422, 'Este pago viene de una renovación y no se puede deshacer.');

        $service->deshacer($pago, $motivo);

        return back()->with('ok', 'Pago deshecho; las cuotas se recalcularon.');
    }
}
