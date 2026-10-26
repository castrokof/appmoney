<?php

namespace App\Http\Controllers;

use App\Concerns\ScopesByUser;
use App\Models\Auditoria;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
    use ScopesByUser;

    public function index(Request $request)
    {
        $desde = $request->query('desde', today()->subDays(30)->toDateString());
        $hasta = $request->query('hasta', today()->toDateString());
        $usuarioId = $request->integer('user_id') ?: null;
        $accion = $request->query('accion');

        $registros = Auditoria::with('user')
            ->when(! auth()->user()->esAdmin(), fn ($q) => $q->whereIn('user_id', $this->userIds()))
            ->whereBetween('created_at', [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->when($usuarioId, fn ($q) => $q->where('user_id', $usuarioId))
            ->when($accion && isset(Auditoria::ACCIONES[$accion]), fn ($q) => $q->where('accion', $accion))
            ->latest('id')->paginate(25)->withQueryString();

        $usuarios = $this->usuariosVisibles();

        return view('auditoria.index', compact('registros', 'usuarios', 'desde', 'hasta', 'usuarioId', 'accion'));
    }
}
