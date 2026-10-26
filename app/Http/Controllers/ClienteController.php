<?php

namespace App\Http\Controllers;

use App\Concerns\ScopesByUser;
use App\Models\Cliente;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    use ScopesByUser;

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $clientes = Cliente::with('user')
            ->whereIn('user_id', $this->userIds())
            ->when($q, fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('nombres', 'like', "%$q%")
                    ->orWhere('apellidos', 'like', "%$q%")
                    ->orWhere('documento', 'like', "%$q%")
                    ->orWhere('telefono', 'like', "%$q%");
            }))
            ->orderBy('orden')->orderBy('nombres')
            ->paginate(20)->withQueryString();

        return view('clientes.index', compact('clientes', 'q'));
    }

    public function create()
    {
        return view('clientes.form', [
            'cliente' => new Cliente(['orden' => 0, 'activo' => true]),
            'usuarios' => $this->usuariosVisibles(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);
        $data['user_id'] = $this->userIdPermitido($request->integer('user_id'));

        Cliente::create($data);

        return redirect()->route('clientes.index')->with('ok', 'Cliente creado.');
    }

    public function edit(Cliente $cliente)
    {
        $this->autorizar($cliente);

        return view('clientes.form', [
            'cliente' => $cliente,
            'usuarios' => $this->usuariosVisibles(),
        ]);
    }

    public function update(Request $request, Cliente $cliente)
    {
        $this->autorizar($cliente);

        $data = $this->validar($request);
        if ($request->user()->esGestor() && $request->filled('user_id')) {
            $data['user_id'] = $this->userIdPermitido($request->integer('user_id'));
        }
        $cliente->update($data);

        return redirect()->route('clientes.index')->with('ok', 'Cliente actualizado.');
    }

    public function destroy(Cliente $cliente)
    {
        $this->autorizar($cliente);

        if ($cliente->prestamos()->where('estado', 'activo')->exists()) {
            return back()->withErrors(['cliente' => 'El cliente tiene préstamos activos.']);
        }
        $cliente->delete();

        return redirect()->route('clientes.index')->with('ok', 'Cliente eliminado.');
    }

    private function validar(Request $request): array
    {
        $data = $request->validate([
            'nombres' => 'required|string|max:120',
            'apellidos' => 'nullable|string|max:120',
            'documento' => 'nullable|string|max:30',
            'telefono' => 'nullable|string|max:30',
            'direccion' => 'nullable|string|max:255',
            'barrio' => 'nullable|string|max:120',
            'observacion' => 'nullable|string|max:1000',
            'orden' => 'nullable|integer|min:0',
        ]);
        $data['orden'] = $data['orden'] ?? 0;
        $data['activo'] = $request->boolean('activo', true);

        return $data;
    }
}
