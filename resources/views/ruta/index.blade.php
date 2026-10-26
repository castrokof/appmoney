@extends('layouts.app')
@section('title', 'Ordenar ruta')
@section('subtitle', 'Arrastra para ordenar el recorrido de cobro')
@section('content')
    @if (auth()->user()->esGestor() && $usuarios->count() > 1)
        <form method="GET" class="mb-3">
            <select name="user_id" class="form-select form-select-lg border-0" onchange="this.form.submit()">
                @foreach ($usuarios as $u)
                    <option value="{{ $u->id }}" @selected($userId == $u->id)>Ruta de {{ $u->name }}</option>
                @endforeach
            </select>
        </form>
    @endif

    <div class="card card-soft mb-3"><div class="card-body py-2 d-flex flex-wrap gap-2 align-items-center">
        <span class="small text-secondary me-auto">Ordenar rápido:</span>
        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" data-ordenar="barrio"><i class="bi bi-geo-alt"></i> Por barrio y dirección</button>
        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" data-ordenar="nombre"><i class="bi bi-sort-alpha-down"></i> Por nombre</button>
    </div></div>

    @if ($clientes->isEmpty())
        <div class="card card-soft"><div class="card-body text-center py-5 text-secondary">No hay clientes activos para ordenar.</div></div>
    @else
        <ul class="list-unstyled mb-5" id="listaRuta">
            @foreach ($clientes as $c)
                <li class="card card-soft mb-2" data-id="{{ $c->id }}" data-nombre="{{ $c->nombre_completo }}" data-barrio="{{ $c->barrio }}" data-direccion="{{ $c->direccion }}">
                    <div class="card-body py-2 d-flex align-items-center gap-2">
                        <span class="drag-handle text-secondary fs-4" title="Arrastra"><i class="bi bi-grip-vertical"></i></span>
                        <span class="badge bg-primary rounded-pill num" style="min-width:2rem">{{ $loop->iteration }}</span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-bold text-truncate">{{ $c->nombre_completo }}
                                @if ($c->activos_count)<span class="badge badge-soft-success">Con préstamo</span>@endif</div>
                            <div class="small text-secondary text-truncate"><i class="bi bi-geo-alt"></i> {{ $c->direccion ?: 'Sin dirección' }}{{ $c->barrio ? ' · '.$c->barrio : '' }}</div>
                        </div>
                        <div class="d-flex flex-column">
                            <button type="button" class="btn btn-sm btn-link p-0 lh-1" data-mover="up" aria-label="Subir"><i class="bi bi-chevron-up"></i></button>
                            <button type="button" class="btn btn-sm btn-link p-0 lh-1" data-mover="down" aria-label="Bajar"><i class="bi bi-chevron-down"></i></button>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="ruta-barra">
            <div class="container d-flex align-items-center gap-2">
                <span class="small text-warning-emphasis d-none" id="avisoRuta"><i class="bi bi-exclamation-circle"></i> Cambios sin guardar</span>
                <span class="small text-success d-none" id="okRuta"><i class="bi bi-check-circle"></i> Orden guardado</span>
                <button class="btn btn-success btn-cobrar ms-auto" id="btnGuardarRuta" disabled
                        data-url="{{ route('ruta.guardar') }}" data-user="{{ $userId }}"><i class="bi bi-save"></i> Guardar orden</button>
            </div>
        </div>
        @vite('resources/js/ruta.js')
    @endif
@endsection
