import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

// Formato de dinero (es-CO) reutilizable en las vistas
window.money = (n) => '$' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });
