@extends('layouts.app')
@section('title', 'Nuevo préstamo')
@section('content')
<div class="card card-soft"><div class="card-body">
    <form method="POST" action="{{ route('prestamos.store') }}" id="formPrestamo">
        @csrf
        @if ($usarBase)
            <div class="alert alert-primary py-2 d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bank"></i> Caja disponible del cobrador</span>
                <strong id="cajaTxt">$0</strong>
            </div>
        @endif

        <div class="row g-3">
            <div class="col-12">
                <label class="form-label fw-semibold">Cliente *</label>
                <select name="cliente_id" id="cliente" class="form-select form-select-lg" required>
                    <option value="">Selecciona…</option>
                    @foreach ($clientes as $c)
                        <option value="{{ $c->id }}" data-user="{{ $c->user_id }}" @selected(old('cliente_id', $clienteId) == $c->id)>{{ trim($c->nombres.' '.$c->apellidos) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 d-none" id="boxCal"><div class="alert py-2 mb-0" id="calAlert"></div></div>
            <div class="col-12"><label class="form-label fw-semibold">Descripción</label>
                <input name="descripcion" class="form-control" placeholder="Efectivo, Nevera Samsung…" value="{{ old('descripcion') }}"></div>
            <div class="col-6"><label class="form-label fw-semibold">Monto prestado *</label>
                <input name="monto" id="monto" type="number" step="any" min="1" inputmode="decimal" class="form-control form-control-lg" required value="{{ old('monto') }}"></div>
            <div class="col-6"><label class="form-label fw-semibold">Interés total % *</label>
                <input name="interes_pct" id="interes" type="number" step="any" min="0" inputmode="decimal" class="form-control form-control-lg" required value="{{ old('interes_pct', 20) }}"></div>
            <div class="col-6"><label class="form-label fw-semibold">N.º de cuotas *</label>
                <input name="num_cuotas" id="cuotas" type="number" min="1" class="form-control form-control-lg" required value="{{ old('num_cuotas', 20) }}"></div>
            <div class="col-6"><label class="form-label fw-semibold">Frecuencia *</label>
                <select name="frecuencia" id="frecuencia" class="form-select form-select-lg">
                    @foreach (['diario' => 'Diario', 'semanal' => 'Semanal', 'quincenal' => 'Quincenal', 'mensual' => 'Mensual'] as $k => $v)
                        <option value="{{ $k }}" @selected(old('frecuencia', 'diario') === $k)>{{ $v }}</option>
                    @endforeach
                </select></div>
            <div class="col-6"><label class="form-label fw-semibold">Primera cuota *</label>
                <input name="fecha_inicio" id="inicio" type="date" class="form-control form-control-lg" required value="{{ old('fecha_inicio', today()->addDay()->toDateString()) }}"></div>
            <div class="col-6 d-flex align-items-end" id="boxDomingo">
                <div class="form-check form-switch fs-6 mb-2">
                    <input type="hidden" name="saltar_domingos" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" name="saltar_domingos" value="1" id="domingos" @checked(old('saltar_domingos', true))>
                    <label class="form-check-label" for="domingos">Saltar domingos</label>
                </div>
            </div>
        </div>

        <div class="rounded-4 p-3 mt-4 text-white" style="background:var(--brand-grad)">
            <div class="row text-center">
                <div class="col-4"><div class="small opacity-75">Cuota</div><div class="fs-5 fw-bold" id="pvCuota">$0</div></div>
                <div class="col-4"><div class="small opacity-75">Total a pagar</div><div class="fs-5 fw-bold" id="pvTotal">$0</div></div>
                <div class="col-4"><div class="small opacity-75">Última cuota</div><div class="fs-6 fw-bold" id="pvFin">—</div></div>
            </div>
        </div>
        <div class="text-danger small mt-2 d-none" id="avisoBase"></div>

        <button class="btn btn-success btn-lg w-100 fw-bold mt-4">Crear préstamo</button>
    </form>
</div></div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const cals = @json($cals);
    const cajas = @json($cajas), bloquear = @json($bloquear), usarBase = @json($usarBase), yo = {{ auth()->id() }};
    const $ = (id) => document.getElementById(id);

    function fechaFinal(inicio, n, frec, saltar) {
        let d = new Date(inicio + 'T00:00:00');
        saltar = saltar && frec === 'diario';
        if (saltar && d.getDay() === 0) d.setDate(d.getDate() + 1);
        for (let i = 1; i < n; i++) {
            if (frec === 'diario') d.setDate(d.getDate() + 1);
            else if (frec === 'semanal') d.setDate(d.getDate() + 7);
            else if (frec === 'quincenal') d.setDate(d.getDate() + 15);
            else { const dia = d.getDate(); d.setDate(1); d.setMonth(d.getMonth() + 1);
                   const max = new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate(); d.setDate(Math.min(dia, max)); }
            if (saltar && d.getDay() === 0) d.setDate(d.getDate() + 1);
        }
        return d;
    }

    function actualizar() {
        const monto = parseFloat($('monto').value) || 0, int = parseFloat($('interes').value) || 0;
        const n = parseInt($('cuotas').value) || 1, frec = $('frecuencia').value;
        const total = monto * (1 + int / 100);
        $('pvTotal').textContent = money(total);
        $('pvCuota').textContent = money(Math.round(total / n));
        $('boxDomingo').style.visibility = frec === 'diario' ? 'visible' : 'hidden';
        if ($('inicio').value) $('pvFin').textContent = fechaFinal($('inicio').value, n, frec, $('domingos').checked)
            .toLocaleDateString('es-CO', { day: '2-digit', month: 'short', year: 'numeric' });

        const opt = $('cliente').selectedOptions[0];
        const uid = opt && opt.dataset.user ? opt.dataset.user : yo;
        const cal = opt && opt.value ? cals[opt.value] : null;
        const box = $('boxCal');
        if (cal) {
            const txt = cal.puntaje === null ? 'Cliente nuevo: aún sin historial.'
                : 'Calificación ' + cal.etiqueta + ' (' + cal.puntaje + '/100)' + (cal.atrasadas ? ' · ' + cal.atrasadas + ' cuota(s) atrasada(s), la más vieja hace ' + cal.dias_max + ' día(s)' : '');
            $('calAlert').className = 'alert py-2 mb-0 alert-' + cal.color;
            $('calAlert').textContent = txt;
            box.classList.remove('d-none');
        } else box.classList.add('d-none');
        if (!usarBase) return;
        const caja = cajas[uid] ?? 0;
        $('cajaTxt').textContent = money(caja);
        const av = $('avisoBase');
        if (monto > caja) {
            av.textContent = (bloquear ? 'Base insuficiente: ' : 'Advertencia: ') + 'el monto supera la caja disponible (' + money(caja) + ').';
            av.classList.remove('d-none');
        } else av.classList.add('d-none');
    }

    document.querySelectorAll('#formPrestamo input, #formPrestamo select').forEach(el => el.addEventListener('input', actualizar));
    actualizar();
});
</script>
@endpush
