<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
    }

    // Botón "Instalar app": Android/Chrome lo dispara el navegador; iPhone se explica a mano
    let eventoInstalar = null;
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        eventoInstalar = e;
        document.querySelectorAll('[data-instalar]').forEach((el) => el.classList.remove('d-none'));
    });
    window.addEventListener('appinstalled', () => {
        document.querySelectorAll('[data-instalar], [data-instalar-ios]').forEach((el) => el.classList.add('d-none'));
    });
    document.addEventListener('DOMContentLoaded', () => {
        const standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone;
        const ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
        if (ios && !standalone) {
            document.querySelectorAll('[data-instalar-ios]').forEach((el) => el.classList.remove('d-none'));
        }
        document.querySelectorAll('[data-instalar] button, button[data-instalar]').forEach((b) => b.addEventListener('click', async () => {
            if (!eventoInstalar) return;
            eventoInstalar.prompt();
            await eventoInstalar.userChoice;
            eventoInstalar = null;
            document.querySelectorAll('[data-instalar]').forEach((el) => el.classList.add('d-none'));
        }));
    });
</script>
