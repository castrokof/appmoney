@extends('layouts.app')
@section('title', $prestamo->cliente->nombre_completo)
@section('subtitle', 'Préstamo #'.$prestamo->id.' · '.ucfirst($prestamo->estado))
@section('content')
    @php $badge = ['pagada' => 'badge-soft-success', 'atrasada' => 'badge-soft-danger', 'parcial' => 'badge-soft-warning', 'pendiente' => 'badge-soft-secondary']; @endphp

    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><div class="card card-soft stat"><div class="label">Saldo</div><div class="value">${{ number_format($prestamo->saldo_calc, 0, '', '') }}</div></div></div>
        <div class="col-6 col-md-3"><div class="card card-soft stat"><div class="label">Atrasado</div><div class="value text-danger">${{ number_format($prestamo->atrasado_calc, 0, '', '') }}</div></div></div>
        <div class="col-6 col-md-3"><div class="card card-soft stat"><div class="label">Total préstamo</div><div class="value">${{ number_format($prestamo->monto_total, 0, '', '') }}</div></div></div>
        <div class="col-6 col-md-3"><div class="card card-soft stat"><div class="label">Cuota</div><div class="value">${{ number_format($prestamo->valor_cuota, 0, '', '') }}</div></div></div>
    </div>

    <div class="card card-soft mb-3"><div class="card-body small">
        <div>@if ((float) $prestamo->monto !== (float) $prestamo->monto_total)<strong>Prestado:</strong> ${{ number_format($prestamo->monto, 0, '', '') }} · @endif<strong>Debe:</strong> ${{ number_format($prestamo->monto_total, 0, '', '') }} = {{ $prestamo->num_cuotas }} cuotas de ${{ number_format($prestamo->valor_cuota, 0, '', '') }} {{ $prestamo->frecuencia }}s desde {{ $prestamo->fecha_inicio->format('d/m/Y') }}</div>
        <div><strong>Calificación del cliente:</strong> @include('partials.calificacion', ['cal' => $cal])
            @if ($cal['atrasadas'] > 0)<span class="text-danger">· {{ $cal['atrasadas'] }} cuota(s) atrasada(s), la más vieja hace {{ $cal['dias_max'] }} día(s)</span>@endif</div>
        @if ($prestamo->renovado_de_id)<div><strong>Renovación del préstamo</strong> <a href="{{ route('prestamos.show', $prestamo->renovado_de_id) }}">#{{ $prestamo->renovado_de_id }}</a></div>@endif
        <div><strong>Cobrador:</strong> {{ $prestamo->user->name }} · <strong>Tel:</strong> {{ $prestamo->cliente->telefono ?: '—' }}</div>
        @if ($prestamo->estado === 'activo')
            <div class="d-flex gap-2 mt-3">
                <button class="btn btn-success btn-cobrar flex-grow-1"
                    onclick="abrirPago({{ $prestamo->id }}, @js($prestamo->cliente->nombre_completo), {{ $prestamo->por_cobrar_hoy ?: $prestamo->valor_cuota }}, {{ $prestamo->saldo_calc }})">
                    <i class="bi bi-cash-stack"></i> Registrar pago</button>
                <form method="POST" action="{{ route('prestamos.anular', $prestamo->id) }}" data-motivo="¿Por qué anulas este préstamo?">
                    @csrf <button class="btn btn-outline-danger btn-cobrar">Anular</button>
                </form>
            </div>
        @endif
        @if (in_array($prestamo->estado, ['activo', 'pagado'], true))
            <button class="btn btn-primary btn-cobrar w-100 mt-3" data-bs-toggle="modal" data-bs-target="#modalRenovar">
                <i class="bi bi-arrow-repeat"></i> Renovar préstamo
            </button>
        @endif
    </div></div>

    @if (in_array($prestamo->estado, ['activo', 'pagado'], true))
    <div class="modal fade" id="modalRenovar" tabindex="-1"><div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('prestamos.renovar', $prestamo->id) }}" class="modal-content">
            @csrf
            <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold">Renovar préstamo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small text-secondary">Se crea un préstamo nuevo para {{ $prestamo->cliente->nombre_completo }} con las mismas condiciones ({{ $prestamo->frecuencia }}{{ $prestamo->saltar_domingos ? ', sin domingos' : '' }}{{ $prestamo->saltar_festivos ? ', sin festivos' : '' }}). Puedes ajustar los valores.</p>
                @if ($prestamo->saldo_calc > 0)
                    <div class="alert alert-warning py-2 small">Este préstamo aún tiene <strong>${{ number_format($prestamo->saldo_calc, 0, '', '') }}</strong> pendientes. Se descuentan del nuevo préstamo y al cliente se le entrega la diferencia.</div>
                @endif
                <div class="row g-2">
                    <div class="col-12"><label class="form-label fw-semibold">Monto a prestar</label>
                        <input name="monto" id="rMonto" type="number" step="any" min="1" inputmode="decimal" class="form-control form-control-lg" required value="{{ (float) $prestamo->monto }}">
                        <div class="form-text">Lo que vas a prestar. De este valor se resta el saldo pendiente.</div></div>
                    <div class="col-6"><label class="form-label fw-semibold">N.º de cuotas</label>
                        <input name="num_cuotas" id="rN" type="number" min="1" class="form-control form-control-lg" required value="{{ $prestamo->num_cuotas }}"></div>
                    <div class="col-6"><label class="form-label fw-semibold">Valor de la cuota</label>
                        <input name="valor_cuota" id="rCuota" type="number" step="any" min="1" inputmode="decimal" class="form-control form-control-lg" required value="{{ (float) $prestamo->valor_cuota }}"></div>
                    <div class="col-12"><label class="form-label fw-semibold">Primera cuota</label>
                        <input name="fecha_inicio" type="date" class="form-control" required value="{{ today()->addDay()->toDateString() }}"></div>
                </div>
                <div class="rounded-4 p-3 mt-3 text-white" style="background:var(--brand-grad)">
                    <div class="d-flex justify-content-between"><span class="opacity-75">Monto a prestar</span><strong id="rTotal">$0</strong></div>
                    <div class="d-flex justify-content-between"><span class="opacity-75">− Saldo pendiente actual</span><strong id="rSaldo">$0</strong></div>
                    <div class="d-flex justify-content-between fs-5 mt-1"><span>Entregar al cliente</span><strong id="rEntregar">$0</strong></div>
                    <hr class="my-2 opacity-75">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Total que debe el cliente<br><small class="opacity-75" id="rFormula">cuota × cuotas</small></span>
                        <strong class="fs-4" id="rDebe">$0</strong>
                    </div>
                </div>
                <div class="text-danger small mt-2 d-none" id="rError">El monto a prestar debe cubrir el saldo pendiente.</div>
                <div class="text-danger small mt-2 d-none" id="rErrorTotal">El monto a prestar no puede ser mayor al total que debe el cliente.</div>
            </div>
            <div class="modal-footer border-0 pt-0"><button class="btn btn-success btn-lg w-100 fw-bold" id="rBtn">Renovar ahora</button></div>
        </form>
    </div></div>
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const saldo = {{ (float) $prestamo->saldo_calc }};
            const $ = (id) => document.getElementById(id);
            const f = () => {
                const monto = parseFloat($('rMonto').value) || 0;
                const cuota = parseFloat($('rCuota').value) || 0, n = parseInt($('rN').value) || 0;
                const debe = cuota * n; // lo que debe el cliente = cuota × n.º de cuotas
                $('rTotal').textContent = money(monto);
                $('rSaldo').textContent = money(saldo);
                $('rEntregar').textContent = money(monto - saldo);
                $('rDebe').textContent = money(debe);
                $('rFormula').textContent = n + ' cuotas de ' + money(cuota);
                const corto = monto < saldo;
                const exceso = monto > debe; // lo que se entrega no puede superar lo que el cliente debe devolver
                $('rError').classList.toggle('d-none', !corto);
                $('rErrorTotal').classList.toggle('d-none', !exceso);
                $('rDebe').classList.toggle('text-warning', exceso);
                $('rBtn').disabled = corto || exceso || monto <= 0 || debe <= 0;
            };
            ['rMonto', 'rN', 'rCuota'].forEach((id) => $(id).addEventListener('input', f));
            f();
        });
    </script>
    @endpush
    @endif

    <h2 class="h6 fw-bold text-uppercase text-secondary mt-4">Cuotas</h2>
    <div class="card card-soft mb-3"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>#</th><th>Fecha</th><th class="text-end">Valor</th><th class="text-end">Pagado</th><th></th></tr></thead>
        <tbody>
        @foreach ($prestamo->cuotas as $c)
            <tr>
                <td>{{ $c->numero }}</td>
                <td>
                    @if ($c->estado === 'pendiente' && $prestamo->estado === 'activo')
                        <form method="POST" action="{{ route('cuotas.fecha', $c) }}" class="d-flex" data-motivo="¿Por qué mueves la fecha de la cuota {{ $c->numero }}?">
                            @csrf @method('PATCH')
                            <input type="date" name="fecha" value="{{ $c->fecha->toDateString() }}" class="form-control form-control-sm border-0 bg-transparent px-0" onchange="this.form.requestSubmit()" style="min-width:125px">
                        </form>
                    @else {{ $c->fecha->format('d/m/Y') }} @endif
                </td>
                <td class="text-end">${{ number_format($c->valor, 0, '', '') }}</td>
                <td class="text-end">${{ number_format($c->pagado, 0, '', '') }}</td>
                <td><span class="badge {{ $badge[$c->estado_visual] }}">{{ ucfirst($c->estado_visual) }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table></div></div>

    <h2 class="h6 fw-bold text-uppercase text-secondary mt-4">Historial de pagos</h2>
    <div class="card card-soft"><div class="card-body">
        @forelse ($prestamo->pagos->sortByDesc('id') as $pg)
            <div class="d-flex justify-content-between align-items-center py-2 {{ ! $loop->last ? 'border-bottom' : '' }}">
                <div><strong>${{ number_format($pg->valor, 0, '', '') }}</strong>
                    <div class="small text-secondary">{{ $pg->fecha->format('d/m/Y') }} · {{ $pg->created_at->format('h:i a') }} · {{ $pg->user->name }}{{ $pg->nota ? ' · '.$pg->nota : '' }}</div>
                    @if ($pg->renovacion)<span class="badge badge-soft-primary"><i class="bi bi-arrow-repeat"></i> Renovación (sin efectivo)</span>@endif</div>
                @if ($prestamo->estado !== 'anulado' && ! $pg->renovacion)
                    <form method="POST" action="{{ route('pagos.destroy', $pg) }}" data-motivo="¿Por qué deshaces este pago de ${{ number_format($pg->valor, 0, '', '') }}?">
                        @csrf @method('DELETE') <button class="btn btn-sm btn-outline-danger rounded-pill">Deshacer</button>
                    </form>
                @endif
            </div>
        @empty
            <div class="text-secondary text-center">Sin pagos todavía.</div>
        @endforelse
    </div></div>

    <h2 class="h6 fw-bold text-uppercase text-secondary mt-4"><i class="bi bi-shield-check"></i> Historial de cambios</h2>
    <div class="card card-soft mb-3"><div class="card-body">
        @forelse ($auditoria as $a)
            @php [$etq, $color, $icono] = $a->etiqueta; @endphp
            <div class="py-2 {{ ! $loop->last ? 'border-bottom' : '' }}">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <span class="badge badge-soft-{{ $color === 'primary' ? 'success' : $color }}"><i class="bi {{ $icono }}"></i> {{ $etq }}</span>
                    <span class="small text-secondary text-end">{{ $a->created_at->format('d/m/Y H:i') }}</span>
                </div>
                <div class="small mt-1">{{ $a->resumen }}</div>
                @if ($a->motivo)<div class="small fst-italic text-secondary">Motivo: «{{ $a->motivo }}»</div>@endif
                <div class="small text-secondary">Por {{ $a->user->name ?? 'sistema' }}</div>
            </div>
        @empty
            <div class="text-center text-secondary">Sin cambios registrados.</div>
        @endforelse
    </div></div>

    @include('partials.modal-pagar')
@endsection
