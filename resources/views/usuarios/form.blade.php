@extends('layouts.app')
@section('title', $usuario->exists ? 'Editar usuario' : 'Nuevo usuario')
@section('content')
<div class="card card-soft"><div class="card-body">
    <form method="POST" action="{{ $usuario->exists ? route('usuarios.update', $usuario->id) : route('usuarios.store') }}">
        @csrf @if ($usuario->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label fw-semibold">Nombre *</label>
                <input name="name" class="form-control" required value="{{ old('name', $usuario->name) }}"></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Correo *</label>
                <input name="email" type="email" class="form-control" required value="{{ old('email', $usuario->email) }}"></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Contraseña {{ $usuario->exists ? '(vacío = no cambiar)' : '*' }}</label>
                <input name="password" type="password" minlength="8" class="form-control" autocomplete="new-password" @required(! $usuario->exists)></div>
            <div class="col-md-3"><label class="form-label fw-semibold">Rol</label>
                <select name="role" class="form-select">
                    @foreach (auth()->user()->esAdmin() ? \App\Models\User::ROLES : ['cobrador'] as $r)
                        <option value="{{ $r }}" @selected(old('role', $usuario->role) === $r)>{{ ucfirst($r) }}</option>
                    @endforeach
                </select></div>
            <div class="col-md-3"><label class="form-label fw-semibold">N.º de empresa</label>
                <input name="empresa_id" type="number" min="1" class="form-control" value="{{ old('empresa_id', $usuario->empresa_id) }}" @disabled(! auth()->user()->esAdmin())></div>
            <div class="col-12 form-check ms-2">
                <input type="hidden" name="activo" value="0">
                <input class="form-check-input" type="checkbox" name="activo" value="1" id="activo" @checked(old('activo', $usuario->activo))>
                <label class="form-check-label" for="activo">Usuario activo</label></div>
        </div>
        <div class="d-flex gap-2 mt-4">
            <button class="btn btn-primary btn-lg flex-grow-1 fw-bold">Guardar</button>
            <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary btn-lg">Cancelar</a>
        </div>
    </form>
</div></div>
@endsection
