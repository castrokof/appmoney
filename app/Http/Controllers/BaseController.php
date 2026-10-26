<?php

namespace App\Http\Controllers;

use App\Concerns\ScopesByUser;
use App\Models\Base;
use App\Services\CajaService;
use Illuminate\Http\Request;

class BaseController extends Controller
{
    use ScopesByUser;

    public function __construct()
    {
        abort_unless(config('cobros.usar_base'), 404);
    }

    public function index(CajaService $caja)
    {
        $usuarios = $this->usuariosVisibles();
        $filas = $usuarios->map(fn ($u) => ['usuario' => $u] + $caja->desglose($u->id));
        $historial = Base::with('user', 'creador')->whereIn('user_id', $this->userIds())
            ->latest('fecha')->latest('id')->limit(50)->get();

        return view('bases.index', compact('usuarios', 'filas', 'historial'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'monto' => 'required|numeric|not_in:0|min:-9999999999|max:9999999999',
            'fecha' => 'nullable|date',
            'nota' => 'nullable|string|max:255',
        ]);

        abort_unless(in_array((int) $data['user_id'], $this->userIds(), true), 403);

        Base::create($data + ['creado_por' => $request->user()->id, 'fecha' => $data['fecha'] ?? today()->toDateString()]);

        return redirect()->route('bases.index')->with('ok', 'Base registrada.');
    }
}
