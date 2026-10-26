<?php

namespace App\Http\Controllers;

use App\Concerns\ScopesByUser;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RutaController extends Controller
{
    use ScopesByUser;

    public function index(Request $request)
    {
        $usuarios = $this->usuariosVisibles();
        $userId = $this->userIdPermitido($request->integer('user_id'));

        $clientes = Cliente::where('user_id', $userId)->where('activo', true)
            ->withCount(['prestamos as activos_count' => fn ($q) => $q->where('estado', 'activo')])
            ->orderBy('orden')->orderBy('nombres')->get();

        return view('ruta.index', compact('clientes', 'usuarios', 'userId'));
    }

    /** Guarda el nuevo orden: recibe los ids de los clientes en el orden de visita. */
    public function guardar(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'nullable|integer',
            'orden' => 'required|array|min:1',
            'orden.*' => 'integer|distinct',
        ]);

        $userId = $this->userIdPermitido($data['user_id'] ?? null);

        $ids = Cliente::whereIn('id', $data['orden'])->where('user_id', $userId)->pluck('id')->all();
        // Todos los ids deben ser clientes de ese cobrador
        abort_unless(count($ids) === count($data['orden']), 403);

        DB::transaction(function () use ($data) {
            foreach (array_values($data['orden']) as $i => $id) {
                Cliente::where('id', $id)->update(['orden' => $i + 1]);
            }
        });

        return response()->json(['ok' => true]);
    }
}
