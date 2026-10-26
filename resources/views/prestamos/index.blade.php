@extends('layouts.app')
@section('title', 'Préstamos')
@section('content')
    <div class="d-flex gap-2 mb-3">
        <form method="GET" class="flex-grow-1 d-flex gap-2">
            <select name="estado" class="form-select form-select-lg border-0" style="max-width:150px" onchange="this.form.submit()">
                @foreach (['activo' => 'Activos', 'pagado' => 'Pagados', 'anulado' => 'Anulados', 'todos' => 'Todos'] as $k => $v)
                    <option value="{{ $k }}" @selected($estado === $k)>{{ $v }}</option>
                @endforeach
            </select>
            <input name="q" value="{{ $q }}" class="form-control form-control-lg border-0" placeholder="Buscar cliente">
        </form>
        <a href="{{ route('prestamos.create') }}" class="btn btn-primary btn-lg"><i class="bi bi-plus-lg"></i></a>
    </div>

    @forelse ($prestamos as $p)
        <a href="{{ route('prestamos.show', $p->id) }}" class="text-decoration-none text-reset">
            <div class="card card-soft mb-2"><div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-bold">{{ $p->cliente->nombre_completo }}</div>
                    <div class="small text-secondary">{{ $p->descripcion ?: 'Préstamo #'.$p->id }} · {{ $p->num_cuotas }} cuotas {{ $p->frecuencia }}s</div>
                    <span class="badge {{ ['activo' => 'badge-soft-success', 'pagado' => 'badge-soft-secondary', 'anulado' => 'badge-soft-danger'][$p->estado] }}">{{ ucfirst($p->estado) }}</span>
                    @if ($p->cuotas_atrasadas_calc > 0)<span class="badge badge-soft-danger">{{ $p->cuotas_atrasadas_calc }} atrasada(s)</span>@endif
                </div>
                <div class="text-end">
                    <div class="small text-secondary">Saldo</div>
                    <div class="fs-5 fw-bolder">${{ number_format($p->saldo_calc, 0, ',', '.') }}</div>
                </div>
            </div></div>
        </a>
    @empty
        <div class="card card-soft"><div class="card-body text-center py-5 text-secondary">No hay préstamos.</div></div>
    @endforelse

    {{ $prestamos->links() }}
@endsection
