@extends('layouts.app')
@section('title', 'Atrasados')
@section('subtitle', '$'.number_format($totalAtrasado, 0, ',', '.').' en atraso')
@section('content')
    <form class="mb-3" method="GET">
        <div class="input-group input-group-lg">
            <span class="input-group-text bg-white border-0"><i class="bi bi-search"></i></span>
            <input name="q" value="{{ $q }}" class="form-control border-0" placeholder="Buscar cliente">
        </div>
    </form>

    @forelse ($prestamos as $p)
        @php $c = $p->cliente; @endphp
        <div class="card card-soft mb-2">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-bold">{{ $c->nombre_completo }}</div>
                    @include('partials.calificacion', ['cal' => $cals[$c->id] ?? null])
                    <div class="small text-secondary">{{ $c->telefono }} · {{ $p->cuotas_atrasadas_calc }} cuota(s) atrasada(s)</div>
                </div>
                <div class="text-end">
                    <div class="fs-5 fw-bolder text-danger">${{ number_format($p->atrasado_calc, 0, ',', '.') }}</div>
                    <button class="btn btn-sm btn-success rounded-pill"
                        onclick="abrirPago({{ $p->id }}, @js($c->nombre_completo), {{ $p->atrasado_calc }}, {{ $p->saldo_calc }})">Cobrar</button>
                    <a class="btn btn-sm btn-outline-primary rounded-pill" href="{{ route('prestamos.show', $p->id) }}">Ver</a>
                </div>
            </div>
        </div>
    @empty
        <div class="card card-soft"><div class="card-body text-center py-5 text-secondary">
            <i class="bi bi-check-circle display-4 text-success"></i>
            <p class="mt-3 mb-0">Sin préstamos atrasados. ¡Excelente!</p>
        </div></div>
    @endforelse

    @include('partials.modal-pagar')
@endsection
