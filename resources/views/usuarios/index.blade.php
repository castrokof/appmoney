@extends('layouts.app')
@section('title', 'Usuarios')
@section('content')
    <div class="d-flex justify-content-end mb-3">
        <a href="{{ route('usuarios.create') }}" class="btn btn-primary btn-lg"><i class="bi bi-plus-lg"></i> Nuevo</a>
    </div>
    @foreach ($usuarios as $u)
        <div class="card card-soft mb-2"><div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <div class="fw-bold">{{ $u->name }} @unless($u->activo)<span class="badge badge-soft-danger">Inactivo</span>@endunless</div>
                <div class="small text-secondary">{{ $u->email }} · {{ ucfirst($u->role) }}{{ $u->empresa_id ? ' · Empresa '.$u->empresa_id : '' }}</div>
            </div>
            <a class="btn btn-sm btn-outline-secondary rounded-circle" href="{{ route('usuarios.edit', $u->id) }}"><i class="bi bi-pencil"></i></a>
        </div></div>
    @endforeach
@endsection
