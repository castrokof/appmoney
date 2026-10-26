@extends('layouts.app')
@section('title', 'Gastos')
@section('subtitle', 'Total del mes: $'.number_format($total, 0, ',', '.'))
@section('content')
    <div class="d-flex gap-2 mb-3">
        <form method="GET" class="flex-grow-1">
            <input type="month" name="mes" value="{{ $mes }}" class="form-control form-control-lg border-0" onchange="this.form.submit()">
        </form>
        <button class="btn btn-primary btn-lg" onclick="nuevoGasto()"><i class="bi bi-plus-lg"></i></button>
    </div>

    @forelse ($gastos as $g)
        <div class="card card-soft mb-2"><div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <div class="fw-bold">{{ $g->descripcion }}</div>
                <div class="small text-secondary">{{ $g->fecha->format('d/m/Y') }}@if(auth()->user()->esGestor()) · {{ $g->user->name }}@endif</div>
            </div>
            <div class="text-end">
                <div class="fs-5 fw-bolder text-danger">-${{ number_format($g->monto, 0, ',', '.') }}</div>
                @if ($g->user_id === auth()->id() || auth()->user()->esGestor())
                    <button class="btn btn-sm btn-outline-secondary rounded-circle"
                        data-gasto="{{ json_encode(['id' => $g->id, 'monto' => (float) $g->monto, 'descripcion' => $g->descripcion, 'fecha' => $g->fecha->toDateString()]) }}" onclick="editarGasto(JSON.parse(this.dataset.gasto))"><i class="bi bi-pencil"></i></button>
                    <form method="POST" action="{{ route('gastos.destroy', $g) }}" class="d-inline" data-motivo="¿Por qué eliminas este gasto?">
                        @csrf @method('DELETE') <button class="btn btn-sm btn-outline-danger rounded-circle"><i class="bi bi-trash"></i></button>
                    </form>
                @endif
            </div>
        </div></div>
    @empty
        <div class="card card-soft"><div class="card-body text-center py-5 text-secondary">Sin gastos en este mes.</div></div>
    @endforelse

    <div class="modal fade" id="modalGasto" tabindex="-1"><div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content" id="formGasto">
            @csrf <span id="metodoGasto"></span>
            <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold" id="tituloGasto">Nuevo gasto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label class="form-label fw-semibold">Monto</label>
                <input name="monto" id="gMonto" type="number" step="any" min="1" inputmode="decimal" class="form-control form-control-lg" required>
                <label class="form-label fw-semibold mt-3">Descripción</label>
                <input name="descripcion" id="gDesc" maxlength="150" class="form-control" required>
                <label class="form-label fw-semibold mt-3">Fecha</label>
                <input name="fecha" id="gFecha" type="date" class="form-control">
                @if ($cajaActual !== null)<div class="small text-secondary mt-2">Tu caja actual: ${{ number_format($cajaActual, 0, ',', '.') }}</div>@endif
            </div>
            <div class="modal-footer border-0 pt-0"><button class="btn btn-primary btn-lg w-100 fw-bold">Guardar</button></div>
        </form>
    </div></div>
@endsection

@push('scripts')
<script>
    let modalGasto = null;
    function abrirGasto(titulo, action, metodo, g) {
        modalGasto ??= new bootstrap.Modal('#modalGasto');
        document.getElementById('tituloGasto').textContent = titulo;
        document.getElementById('formGasto').action = action;
        document.getElementById('metodoGasto').innerHTML = metodo ? '<input type="hidden" name="_method" value="' + metodo + '">' : '';
        document.getElementById('gMonto').value = g.monto ?? '';
        document.getElementById('gDesc').value = g.descripcion ?? '';
        document.getElementById('gFecha').value = g.fecha ?? new Date().toISOString().slice(0, 10);
        modalGasto.show();
    }
    const nuevoGasto = () => abrirGasto('Nuevo gasto', '{{ route('gastos.store') }}', null, {});
    const editarGasto = (g) => abrirGasto('Editar gasto', '{{ url('gastos') }}/' + g.id, 'PUT', g);
</script>
@endpush
