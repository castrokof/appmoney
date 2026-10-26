<?php

namespace App\Http\Controllers;

use App\Models\User;
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

        User::create($data);

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

        $usuario->update($data);

        return redirect()->route('usuarios.index')->with('ok', 'Usuario actualizado.');
    }

    public function destroy(int $id)
    {
        $usuario = $this->alcance()->findOrFail($id);
        abort_if($usuario->id === auth()->id(), 422, 'No puedes desactivarte a ti mismo.');

        $usuario->update(['activo' => false]);

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
