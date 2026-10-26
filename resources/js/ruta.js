import Sortable from 'sortablejs';

const lista = document.getElementById('listaRuta');
const btnGuardar = document.getElementById('btnGuardarRuta');
const aviso = document.getElementById('avisoRuta');
let sinGuardar = false;

function marcar(cambio) {
    sinGuardar = cambio;
    btnGuardar.disabled = !cambio;
    aviso.classList.toggle('d-none', !cambio);
    document.querySelectorAll('#listaRuta .num').forEach((el, i) => (el.textContent = i + 1));
}

Sortable.create(lista, {
    handle: '.drag-handle',
    animation: 150,
    ghostClass: 'ruta-ghost',
    onEnd: () => marcar(true),
});

// Ordenes rápidos: por barrio y dirección, o por nombre
function ordenar(clave) {
    const items = [...lista.children];
    const norm = (s) => (s || '').toString().toLowerCase();
    items.sort((a, b) => {
        if (clave === 'barrio') {
            return norm(a.dataset.barrio).localeCompare(norm(b.dataset.barrio), 'es')
                || norm(a.dataset.direccion).localeCompare(norm(b.dataset.direccion), 'es', { numeric: true });
        }
        return norm(a.dataset.nombre).localeCompare(norm(b.dataset.nombre), 'es');
    });
    items.forEach((el) => lista.appendChild(el));
    marcar(true);
}
document.querySelectorAll('[data-ordenar]').forEach((b) => b.addEventListener('click', () => ordenar(b.dataset.ordenar)));

// Mover con botones (alternativa a arrastrar)
lista.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-mover]');
    if (!btn) return;
    const item = btn.closest('li');
    if (btn.dataset.mover === 'up' && item.previousElementSibling) lista.insertBefore(item, item.previousElementSibling);
    if (btn.dataset.mover === 'down' && item.nextElementSibling) lista.insertBefore(item.nextElementSibling, item);
    marcar(true);
});

btnGuardar.addEventListener('click', async () => {
    btnGuardar.disabled = true;
    const r = await fetch(btnGuardar.dataset.url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json', Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
        },
        body: JSON.stringify({ user_id: btnGuardar.dataset.user, orden: [...lista.children].map((li) => Number(li.dataset.id)) }),
    });
    if (r.ok) {
        marcar(false);
        const ok = document.getElementById('okRuta');
        ok.classList.remove('d-none');
        setTimeout(() => ok.classList.add('d-none'), 2500);
    } else {
        btnGuardar.disabled = false;
        alert('No se pudo guardar el orden. Intenta de nuevo.');
    }
});

window.addEventListener('beforeunload', (e) => { if (sinGuardar) { e.preventDefault(); e.returnValue = ''; } });
marcar(false);
