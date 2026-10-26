<?php

namespace App\Http\Controllers;

use App\Concerns\ScopesByUser;
use App\Models\Gasto;
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
        $cajaActual = $caja->caja(auth()->id());
        $mes = $inicio->format('Y-m');

        return view('gastos.index', compact('gastos', 'total', 'mes', 'cajaActual'));
    }

    public function store(Request $request, CajaService $caja)
    {
        $data = $this->validar($request);
        $data['user_id'] = auth()->id();

        $advertencia = null;
        if ($data['monto'] > $caja->caja($data['user_id'])) {
            $advertencia = ' Atención: el gasto deja la caja en negativo.';
        }

        Gasto::create($data);

        return redirect()->route('gastos.index')->with('ok', 'Gasto registrado.'.$advertencia);
    }

    public function update(Request $request, Gasto $gasto)
    {
        $this->autorizar($gasto);
        $gasto->update($this->validar($request));

        return redirect()->route('gastos.index')->with('ok', 'Gasto actualizado.');
    }

    public function destroy(Gasto $gasto)
    {
        $this->autorizar($gasto);
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
