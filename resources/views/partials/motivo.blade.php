{{-- Ventana común para pedir el motivo. Cualquier <form data-motivo="Pregunta"> la abre antes de enviarse. --}}
<div class="modal fade" id="modalMotivo" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="formMotivo" novalidate>
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-check text-primary"></i> Motivo del cambio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary mb-2" id="motivoPregunta"></p>
                <textarea id="motivoTexto" class="form-control" rows="3" maxlength="255" minlength="5" required
                          placeholder="Escribe el motivo (mínimo 5 letras)"></textarea>
                <div class="text-danger small mt-1 d-none" id="motivoError">El motivo es obligatorio (mínimo 5 caracteres).</div>
                <div class="small text-secondary mt-2">Quedará guardado en el historial de cambios con tu nombre y la hora.</div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button class="btn btn-danger btn-lg w-100 fw-bold">Confirmar</button>
            </div>
        </form>
    </div>
</div>

<script>
    (() => {
        let formActivo = null, confirmado = false, modal = null;
        const $ = (id) => document.getElementById(id);

        document.addEventListener('submit', (e) => {
            const f = e.target;
            if (!(f instanceof HTMLFormElement) || !f.hasAttribute('data-motivo') || f.dataset.motivoListo) return;
            e.preventDefault();
            formActivo = f; confirmado = false;
            $('motivoPregunta').textContent = f.getAttribute('data-motivo');
            $('motivoTexto').value = '';
            $('motivoError').classList.add('d-none');
            modal ??= new bootstrap.Modal('#modalMotivo');
            modal.show();
            setTimeout(() => $('motivoTexto').focus(), 400);
        });

        document.addEventListener('DOMContentLoaded', () => {
            $('formMotivo').addEventListener('submit', (e) => {
                e.preventDefault();
                const motivo = $('motivoTexto').value.trim();
                if (motivo.length < 5) { $('motivoError').classList.remove('d-none'); return; }
                confirmado = true;
                const h = document.createElement('input');
                h.type = 'hidden'; h.name = 'motivo'; h.value = motivo;
                formActivo.appendChild(h);
                formActivo.dataset.motivoListo = '1';
                formActivo.submit();
            });
            // Si se cierra sin confirmar, se deshace el cambio de la fecha en pantalla
            $('modalMotivo').addEventListener('hidden.bs.modal', () => {
                if (!confirmado && formActivo) formActivo.reset();
            });
        });
    })();
</script>
