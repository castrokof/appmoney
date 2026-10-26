@extends('layouts.app')
@section('title', $prestamo->cliente->nombre_completo)
@section('subtitle', ($prestamo->descripcion ?: 'Préstamo #'.$prestamo->id).' · '.ucfirst($prestamo->estado))
@section('content')
    @php $badge = ['pagada' => 'badge-soft-success', 'atrasada' => 'badge-soft-danger', 'parcial' => 'badge-soft-warning', 'pendiente' => 'badge-soft-secondary']; @endphp

    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><div class="card card-soft stat"><div class="label">Saldo</div><div class="value">${{ number_format($prestamo->saldo_calc, 0, ',', '.') }}</div></div></div>
        <div class="col-6 col-md-3"><div class="card card-soft stat"><div class="label">Atrasado</div><div class="value text-danger">${{ number_format($prestamo->atrasado_calc, 0, ',', '.') }}</div></div></div>
        <div class="col-6 col-md-3"><div class="card card-soft stat"><div class="label">Total préstamo</div><div class="value">${{ number_format($prestamo->monto_total, 0, ',', '.') }}</div></div></div>
        <div class="col-6 col-md-3"><div class="card card-soft stat"><div class="label">Cuota</div><div class="value">${{ number_format($prestamo->valor_cuota, 0, ',', '.') }}</div></div></div>
    </div>

    <div class="card card-soft mb-3"><div class="card-body small">
        <div><strong>Prestado:</strong> ${{ number_format($prestamo->monto, 0, ',', '.') }} al {{ rtrim(rtrim($prestamo->interes_pct, '0'), '.') }}% · {{ $prestamo->num_cuotas }} cuotas {{ $prestamo->frecuencia }}s desde {{ $prestamo->fecha_inicio->format('d/m/Y') }}</div>
        <div><strong>Cobrador:</strong> {{ $prestamo->user->name }} · <strong>Tel:</strong> {{ $prestamo->cliente->telefono ?: '—' }}</div>
        @if ($prestamo->estado === 'activo')
            <div class="d-flex gap-2 mt-3">
                <button class="btn btn-success btn-cobrar flex-grow-1"
                    onclick="abrirPago({{ $prestamo->id }}, @js($prestamo->cliente->nombre_completo), {{ $prestamo->por_cobrar_hoy ?: $prestamo->valor_cuota }}, {{ $prestamo->saldo_calc }})">
                    <i class="bi bi-cash-stack"></i> Registrar pago</button>
                <form method="POST" action="{{ route('prestamos.anular', $prestamo->id) }}" onsubmit="return confirm('¿Anular este préstamo?')">
                    @csrf <button class="btn btn-outline-danger btn-cobrar">Anular</button>
                </form>
            </div>
        @endif
    </div></div>

    <h2 class="h6 fw-bold text-uppercase text-secondary mt-4">Cuotas</h2>
    <div class="card card-soft mb-3"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>#</th><th>Fecha</th><th class="text-end">Valor</th><th class="text-end">Pagado</th><th></th></tr></thead>
        <tbody>
        @foreach ($prestamo->cuotas as $c)
            <tr>
                <td>{{ $c->numero }}</td>
                <td>
                    @if ($c->estado === 'pendiente' && $prestamo->estado === 'activo')
                        <form method="POST" action="{{ route('cuotas.fecha', $c) }}" class="d-flex">
                            @csrf @method('PATCH')
                            <input type="date" name="fecha" value="{{ $c->fecha->toDateString() }}" class="form-control form-control-sm border-0 bg-transparent px-0" onchange="this.form.submit()" style="min-width:125px">
                        </form>
                    @else {{ $c->fecha->format('d/m/Y') }} @endif
                </td>
                <td class="text-end">${{ number_format($c->valor, 0, ',', '.') }}</td>
                <td class="text-end">${{ number_format($c->pagado, 0, ',', '.') }}</td>
                <td><span class="badge {{ $badge[$c->estado_visual] }}">{{ ucfirst($c->estado_visual) }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table></div></div>

    <h2 class="h6 fw-bold text-uppercase text-secondary mt-4">Historial de pagos</h2>
    <div class="card card-soft"><div class="card-body">
        @forelse ($prestamo->pagos->sortByDesc('id') as $pg)
            <div class="d-flex justify-content-between align-items-center py-2 {{ ! $loop->last ? 'border-bottom' : '' }}">
                <div><strong>${{ number_format($pg->valor, 0, ',', '.') }}</strong>
                    <div class="small text-secondary">{{ $pg->fecha->format('d/m/Y') }} · {{ $pg->user->name }}{{ $pg->nota ? ' · '.$pg->nota : '' }}</div></div>
                @if ($prestamo->estado !== 'anulado')
                    <form method="POST" action="{{ route('pagos.destroy', $pg) }}" onsubmit="return confirm('¿Deshacer este pago?')">
                        @csrf @method('DELETE') <button class="btn btn-sm btn-outline-danger rounded-pill">Deshacer</button>
                    </form>
                @endif
            </div>
        @empty
            <div class="text-secondary text-center">Sin pagos todavía.</div>
        @endforelse
    </div></div>

    @include('partials.modal-pagar')
@endsection
