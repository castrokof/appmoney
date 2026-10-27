@extends('layouts.app')
@section('title', 'Base de dinero')
@section('subtitle', 'Caja disponible por cobrador')
@section('content')
    <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#modalBase"><i class="bi bi-plus-lg"></i> Entregar base</button>
    </div>

    @foreach ($filas as $f)
        <div class="card card-soft mb-2"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="fw-bold">{{ $f['usuario']->name }}</div>
                <div class="fs-4 fw-bolder {{ $f['caja'] < 0 ? 'text-danger' : 'text-success' }}">${{ number_format($f['caja'], 0, '', '') }}</div>
            </div>
            <div class="row small text-secondary text-center g-1">
                <div class="col-3">Base<br><strong class="text-dark">${{ number_format($f['base'], 0, '', '') }}</strong></div>
                <div class="col-3">Cobrado<br><strong class="text-dark">${{ number_format($f['cobrado'], 0, '', '') }}</strong></div>
                <div class="col-3">Prestado<br><strong class="text-dark">${{ number_format($f['prestado'], 0, '', '') }}</strong></div>
                <div class="col-3">Gastos<br><strong class="text-dark">${{ number_format($f['gastos'], 0, '', '') }}</strong></div>
            </div>
        </div></div>
    @endforeach

    <h2 class="h6 fw-bold text-uppercase text-secondary mt-4">Historial de entregas</h2>
    <div class="card card-soft"><div class="card-body">
        @forelse ($historial as $b)
            <div class="d-flex justify-content-between py-2 {{ ! $loop->last ? 'border-bottom' : '' }}">
                <div><strong>{{ $b->user->name }}</strong>
                    <div class="small text-secondary">{{ $b->fecha->format('d/m/Y') }} · por {{ $b->creador->name }}{{ $b->nota ? ' · '.$b->nota : '' }}</div></div>
                <strong class="{{ $b->monto < 0 ? 'text-danger' : 'text-success' }}">{{ $b->monto < 0 ? '-' : '+' }}${{ number_format(abs($b->monto), 0, '', '') }}</strong>
            </div>
        @empty
            <div class="text-center text-secondary">Aún no hay entregas de base.</div>
        @endforelse
    </div></div>

    <div class="modal fade" id="modalBase" tabindex="-1"><div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('bases.store') }}" class="modal-content">
            @csrf
            <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold">Entregar base</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label class="form-label fw-semibold">Cobrador</label>
                <select name="user_id" class="form-select form-select-lg" required>
                    @foreach ($usuarios as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                </select>
                <label class="form-label fw-semibold mt-3">Monto (negativo = devolución)</label>
                <input name="monto" type="number" step="any" inputmode="decimal" class="form-control form-control-lg" required>
                <label class="form-label fw-semibold mt-3">Fecha</label>
                <input name="fecha" type="date" value="{{ today()->toDateString() }}" class="form-control">
                <label class="form-label fw-semibold mt-3">Nota</label>
                <input name="nota" maxlength="255" class="form-control">
            </div>
            <div class="modal-footer border-0 pt-0"><button class="btn btn-primary btn-lg w-100 fw-bold">Guardar</button></div>
        </form>
    </div></div>
@endsection
