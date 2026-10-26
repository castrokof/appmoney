@extends('layouts.app')
@section('title', 'Cobro de hoy')
@section('content')
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><div class="card card-soft stat"><div class="label">Por cobrar hoy</div><div class="value text-primary">${{ number_format($porCobrar, 0, ',', '.') }}</div></div></div>
        <div class="col-6 col-md-3"><div class="card card-soft stat"><div class="label">Cobrado hoy</div><div class="value text-success">${{ number_format($cobradoHoy, 0, ',', '.') }}</div></div></div>
        @if ($cajaTotal !== null)
            <div class="col-6 col-md-3"><div class="card card-soft stat"><div class="label">Caja disponible</div><div class="value {{ $cajaTotal < 0 ? 'text-danger' : '' }}">${{ number_format($cajaTotal, 0, ',', '.') }}</div></div></div>
        @else
            <div class="col-6 col-md-3"><div class="card card-soft stat"><div class="label">Atrasado</div><div class="value text-danger">${{ number_format($atrasadoTotal, 0, ',', '.') }}</div></div></div>
        @endif
        <div class="col-6 col-md-3"><div class="card card-soft stat"><div class="label">Clientes a visitar</div><div class="value">{{ $prestamos->count() }}</div></div></div>
    </div>

    <form class="mb-3" method="GET">
        <div class="input-group input-group-lg">
            <span class="input-group-text bg-white border-0"><i class="bi bi-search"></i></span>
            <input name="q" value="{{ $q }}" class="form-control border-0" placeholder="Buscar cliente o dirección">
        </div>
    </form>

    @forelse ($prestamos as $p)
        @php $c = $p->cliente; @endphp
        <div class="card card-soft mb-2">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="me-2">
                        <div class="fw-bold">{{ $c->nombre_completo }}</div>
                        @include('partials.calificacion', ['cal' => $cals[$c->id] ?? null])
                        <div class="small text-secondary"><i class="bi bi-geo-alt"></i> {{ $c->direccion ?: 'Sin dirección' }}{{ $c->barrio ? ' · '.$c->barrio : '' }}</div>
                        @include('partials.cuota-actual', ['p' => $p])
                    </div>
                    <div class="text-end">
                        <div class="fs-5 fw-bolder text-primary">${{ number_format($p->hoy_calc, 0, ',', '.') }}</div>
                        @if ($p->cuotas_atrasadas_calc > 0)
                            <span class="badge badge-soft-danger">{{ $p->cuotas_atrasadas_calc }} atrasada(s) · ${{ number_format($p->atrasado_calc, 0, ',', '.') }}</span>
                        @endif
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3 align-items-center">
                    <button class="btn btn-success btn-cobrar flex-grow-1"
                        onclick="abrirPago({{ $p->id }}, @js($c->nombre_completo), {{ $p->hoy_calc }}, {{ $p->saldo_calc }})">
                        <i class="bi bi-cash-stack"></i> Cobrar
                    </button>
                    @if ($c->telefono_limpio)
                        <a class="btn btn-outline-secondary rounded-circle" href="tel:{{ $c->telefono_limpio }}"><i class="bi bi-telephone"></i></a>
                        <a class="btn btn-outline-success rounded-circle" target="_blank" href="https://wa.me/{{ $c->telefono_limpio }}"><i class="bi bi-whatsapp"></i></a>
                    @endif
                    <a class="btn btn-outline-primary rounded-circle" href="{{ route('prestamos.show', $p->id) }}"><i class="bi bi-eye"></i></a>
                </div>
            </div>
        </div>
    @empty
        <div class="card card-soft"><div class="card-body text-center py-5 text-secondary">
            <i class="bi bi-emoji-smile display-4 text-success"></i>
            <p class="mt-3 mb-0">No hay cobros pendientes para hoy.</p>
        </div></div>
    @endforelse

    @include('partials.modal-pagar')
@endsection
