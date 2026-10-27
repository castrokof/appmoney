import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

// Formato de dinero sin separador de miles ($64000), reutilizable en las vistas
window.money = (n) => '$' + Math.round(Number(n || 0));
