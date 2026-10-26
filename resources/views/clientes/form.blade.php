@extends('layouts.app')
@section('title', $cliente->exists ? 'Editar cliente' : 'Nuevo cliente')
@section('subtitle', $cliente->exists ? $cliente->nombre_completo : 'Datos del cliente')
@section('content')
<div class="card card-soft"><div class="card-body">
    <form method="POST" action="{{ $cliente->exists ? route('clientes.update', $cliente) : route('clientes.store') }}">
        @csrf
        @if ($cliente->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label fw-semibold">Nombres *</label>
                <input name="nombres" class="form-control" required value="{{ old('nombres', $cliente->nombres) }}"></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Apellidos</label>
                <input name="apellidos" class="form-control" value="{{ old('apellidos', $cliente->apellidos) }}"></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Documento</label>
                <input name="documento" class="form-control" value="{{ old('documento', $cliente->documento) }}"></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Teléfono (con indicativo para WhatsApp)</label>
                <input name="telefono" type="tel" class="form-control" placeholder="573001234567" value="{{ old('telefono', $cliente->telefono) }}"></div>
            <div class="col-md-8"><label class="form-label fw-semibold">Dirección</label>
                <input name="direccion" class="form-control" value="{{ old('direccion', $cliente->direccion) }}"></div>
            <div class="col-md-4"><label class="form-label fw-semibold">Barrio</label>
                <input name="barrio" class="form-control" value="{{ old('barrio', $cliente->barrio) }}"></div>
            <div class="col-md-4"><label class="form-label fw-semibold">Orden en la ruta</label>
                <input name="orden" type="number" min="0" class="form-control" value="{{ old('orden', $cliente->orden) }}"></div>
            @if (auth()->user()->esGestor())
                <div class="col-md-8"><label class="form-label fw-semibold">Cobrador</label>
                    <select name="user_id" class="form-select">
                        @foreach ($usuarios as $u)
                            <option value="{{ $u->id }}" @selected(old('user_id', $cliente->user_id ?? auth()->id()) == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select></div>
            @endif
            <div class="col-12"><label class="form-label fw-semibold">Observación</label>
                <textarea name="observacion" rows="2" class="form-control">{{ old('observacion', $cliente->observacion) }}</textarea></div>
            <div class="col-12 form-check ms-2">
                <input type="hidden" name="activo" value="0">
                <input class="form-check-input" type="checkbox" name="activo" value="1" id="activo" @checked(old('activo', $cliente->activo))>
                <label class="form-check-label" for="activo">Cliente activo</label></div>
        </div>
        <div class="d-flex gap-2 mt-4">
            <button class="btn btn-primary btn-lg flex-grow-1 fw-bold">Guardar</button>
            <a href="{{ route('clientes.index') }}" class="btn btn-outline-secondary btn-lg">Cancelar</a>
        </div>
    </form>
    @if ($cliente->exists)
        <form method="POST" action="{{ route('clientes.destroy', $cliente) }}" class="mt-3" onsubmit="return confirm('¿Eliminar este cliente?')">
            @csrf @method('DELETE')
            <button class="btn btn-link text-danger p-0">Eliminar cliente</button>
        </form>
    @endif
</div></div>
@endsection
