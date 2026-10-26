@extends('layouts.app')
@section('title', 'Clientes')
@section('content')
    <div class="d-flex gap-2 mb-3">
        <form method="GET" class="flex-grow-1">
            <div class="input-group input-group-lg">
                <span class="input-group-text bg-white border-0"><i class="bi bi-search"></i></span>
                <input name="q" value="{{ $q }}" class="form-control border-0" placeholder="Nombre, documento o teléfono">
            </div>
        </form>
        <a href="{{ route('clientes.create') }}" class="btn btn-primary btn-lg"><i class="bi bi-plus-lg"></i></a>
    </div>

    @forelse ($clientes as $c)
        <div class="card card-soft mb-2">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-bold">{{ $c->nombre_completo }} @unless($c->activo)<span class="badge badge-soft-secondary">Inactivo</span>@endunless</div>
                    <div class="small text-secondary">{{ $c->telefono ?: 'Sin teléfono' }} · {{ $c->direccion ?: 'Sin dirección' }}</div>
                    @if (auth()->user()->esGestor())<div class="small text-secondary">Cobrador: {{ $c->user->name }}</div>@endif
                </div>
                <div class="d-flex gap-1">
                    <a class="btn btn-sm btn-success rounded-pill" href="{{ route('prestamos.create', ['cliente_id' => $c->id]) }}">Prestar</a>
                    <a class="btn btn-sm btn-outline-secondary rounded-circle" href="{{ route('clientes.edit', $c) }}"><i class="bi bi-pencil"></i></a>
                </div>
            </div>
        </div>
    @empty
        <div class="card card-soft"><div class="card-body text-center py-5 text-secondary">No hay clientes. Crea el primero con el botón +.</div></div>
    @endforelse

    {{ $clientes->links() }}
@endsection
