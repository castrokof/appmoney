{{-- Modal único de cobro. Se abre con abrirPago(prestamoId, cliente, sugerido, saldo) --}}
<div class="modal fade" id="modalPago" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="formPago">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="pagoCliente">Cobrar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="small text-secondary mb-2">Saldo del préstamo: <strong id="pagoSaldo"></strong></div>
                <label class="form-label fw-semibold">Valor recibido</label>
                <input type="number" step="any" min="1" inputmode="decimal" id="pagoValor"
                       class="form-control form-control-lg fs-3 fw-bold text-center" required>
                <label class="form-label fw-semibold mt-3">Nota (opcional)</label>
                <input type="text" id="pagoNota" maxlength="255" class="form-control">
                <div class="text-danger small mt-2" id="pagoError"></div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button class="btn btn-success btn-lg w-100 fw-bold" id="pagoBtn">Registrar pago</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    let pagoUrl = null;
    let modalPago = null;

    function abrirPago(id, cliente, sugerido, saldo) {
        pagoUrl = '{{ url('prestamos') }}/' + id + '/pagos';
        document.getElementById('pagoCliente').textContent = cliente;
        document.getElementById('pagoSaldo').textContent = money(saldo);
        document.getElementById('pagoValor').value = Math.round(sugerido);
        document.getElementById('pagoValor').max = saldo;
        document.getElementById('pagoNota').value = '';
        document.getElementById('pagoError').textContent = '';
        modalPago ??= new bootstrap.Modal('#modalPago');
        modalPago.show();
        setTimeout(() => document.getElementById('pagoValor').select(), 400);
    }

    document.addEventListener('DOMContentLoaded', () => document.getElementById('formPago').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('pagoBtn');
        btn.disabled = true;
        const r = await fetch(pagoUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json', 'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            },
            body: JSON.stringify({
                valor: document.getElementById('pagoValor').value,
                nota: document.getElementById('pagoNota').value,
            }),
        });
        if (r.ok) { location.reload(); return; }
        const j = await r.json().catch(() => ({}));
        document.getElementById('pagoError').textContent =
            (j.errors && Object.values(j.errors).flat().join(' ')) || j.message || 'No se pudo registrar el pago.';
        btn.disabled = false;
    }));
</script>
@endpush
