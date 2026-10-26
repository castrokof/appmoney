<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    /** Usuarios que el actual puede administrar. */
    private function alcance()
    {
        $u = auth()->user();

        return $u->esAdmin()
            ? User::query()
            : User::where('empresa_id', $u->empresa_id)->where('role', 'cobrador');
    }

    public function index()
    {
        return view('usuarios.index', ['usuarios' => $this->alcance()->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('usuarios.form', ['usuario' => new User(['role' => 'cobrador', 'activo' => true, 'empresa_id' => auth()->user()->empresa_id])]);
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);
        $data['password'] = $request->validate(['password' => 'required|string|min:8'])['password'];

        $usuario = User::create($data);
        app(AuditoriaService::class)->registrar('usuario.creado', 'Usuario «'.$usuario->name.'» creado ('.$usuario->role.')', null, 'usuario', $usuario->id,
            null, null, ['email' => $usuario->email, 'role' => $usuario->role, 'empresa_id' => $usuario->empresa_id]);

        return redirect()->route('usuarios.index')->with('ok', 'Usuario creado.');
    }

    public function edit(int $id)
    {
        return view('usuarios.form', ['usuario' => $this->alcance()->findOrFail($id)]);
    }

    public function update(Request $request, int $id)
    {
        $usuario = $this->alcance()->findOrFail($id);
        $data = $this->validar($request, $usuario);

        if ($request->filled('password')) {
            $data['password'] = $request->validate(['password' => 'string|min:8'])['password'];
        }
        if ($usuario->id === auth()->id()) {
            unset($data['role'], $data['activo']); // no te quites tu propio acceso
        }

        $antes = $usuario->only(['name', 'email', 'role', 'empresa_id', 'activo']);
        $usuario->update($data);
        $despues = $usuario->only(['name', 'email', 'role', 'empresa_id', 'activo']);

        if ($antes !== $despues || $request->filled('password')) {
            app(AuditoriaService::class)->registrar('usuario.editado',
                'Usuario «'.$usuario->name.'» editado'.($request->filled('password') ? ' (cambio de contraseña)' : ''),
                null, 'usuario', $usuario->id, null, $antes, $despues);
        }

        return redirect()->route('usuarios.index')->with('ok', 'Usuario actualizado.');
    }

    public function destroy(Request $request, int $id)
    {
        $usuario = $this->alcance()->findOrFail($id);
        abort_if($usuario->id === auth()->id(), 422, 'No puedes desactivarte a ti mismo.');
        $motivo = $request->validate(['motivo' => 'required|string|min:5|max:255'])['motivo'];

        $usuario->update(['activo' => false]);
        app(AuditoriaService::class)->registrar('usuario.desactivado', 'Usuario «'.$usuario->name.'» desactivado', null, 'usuario', $usuario->id, $motivo);

        return redirect()->route('usuarios.index')->with('ok', 'Usuario desactivado.');
    }

    private function validar(Request $request, ?User $usuario = null): array
    {
        $actual = $request->user();
        $roles = $actual->esAdmin() ? User::ROLES : ['cobrador'];

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($usuario?->id)],
            'role' => ['required', Rule::in($roles)],
            'empresa_id' => 'nullable|integer|min:1',
        ]);

        // La empresa solo crea usuarios de su propia empresa
        if (! $actual->esAdmin()) {
            $data['empresa_id'] = $actual->empresa_id;
        }
        $data['activo'] = $request->boolean('activo', true);

        return $data;
    }
}
