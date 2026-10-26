@extends('layouts.app')
@section('title', 'Resumen')
@section('subtitle', \Carbon\Carbon::parse($desde)->format('d/m/Y').' al '.\Carbon\Carbon::parse($hasta)->format('d/m/Y'))
@section('content')
    <form method="GET" class="card card-soft mb-3"><div class="card-body row g-2 align-items-end">
        <div class="col-5"><label class="form-label small mb-0">Desde</label><input type="date" name="desde" value="{{ $desde }}" class="form-control"></div>
        <div class="col-5"><label class="form-label small mb-0">Hasta</label><input type="date" name="hasta" value="{{ $hasta }}" class="form-control"></div>
        <div class="col-2"><button class="btn btn-primary w-100"><i class="bi bi-search"></i></button></div>
    </div></form>

    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><div class="card card-soft stat"><div class="label">Cobrado (período)</div><div class="value text-success">${{ number_format($cobradoPeriodo, 0, ',', '.') }}</div></div></div>
        <div class="col-6 col-md-3"><div class="card card-soft stat"><div class="label">Gastos (período)</div><div class="value text-danger">${{ number_format($gastosPeriodo, 0, ',', '.') }}</div></div></div>
        <div class="col-6 col-md-3"><div class="card card-soft stat"><div class="label">Total en la calle</div><div class="value text-primary">${{ number_format($enLaCalle, 0, ',', '.') }}</div></div></div>
        <div class="col-6 col-md-3"><div class="card card-soft stat"><div class="label">Total atrasado</div><div class="value text-danger">${{ number_format($atrasado, 0, ',', '.') }}</div></div></div>
    </div>

    <h2 class="h6 fw-bold text-uppercase text-secondary mt-4">Por cobrador</h2>
    <div class="card card-soft mb-3"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Cobrador</th><th class="text-end">Cobrado</th><th class="text-end">Prestado</th><th class="text-end">Gastos</th><th class="text-end">Caja</th></tr></thead>
        <tbody>@foreach ($cobradoresRows as $r)
            <tr><td>{{ $r['usuario']->name }}</td>
                <td class="text-end">${{ number_format($r['cobrado_periodo'], 0, ',', '.') }}</td>
                <td class="text-end">${{ number_format($r['prestado'], 0, ',', '.') }}</td>
                <td class="text-end">${{ number_format($r['gastos'], 0, ',', '.') }}</td>
                <td class="text-end fw-bold {{ $r['caja'] < 0 ? 'text-danger' : '' }}">${{ number_format($r['caja'], 0, ',', '.') }}</td></tr>
        @endforeach</tbody>
    </table></div></div>

    <h2 class="h6 fw-bold text-uppercase text-secondary mt-4">Cobrado por día</h2>
    <div class="card card-soft"><div class="card-body">
        @forelse ($porDia as $d)
            <div class="d-flex justify-content-between py-1">
                <span>{{ \Carbon\Carbon::parse($d->fecha)->translatedFormat('D d M') }} <small class="text-secondary">· {{ $d->pagos }} pago(s)</small></span>
                <strong>${{ number_format($d->total, 0, ',', '.') }}</strong>
            </div>
        @empty
            <div class="text-center text-secondary">Sin cobros en el período.</div>
        @endforelse
    </div></div>
@endsection
