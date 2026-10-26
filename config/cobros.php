<?php

return [
    // false (por defecto): sin control de base/caja. true: activa bases, caja y validación al prestar.
    'usar_base' => env('COBROS_USAR_BASE', false),

    // Solo aplica si usar_base=true. true: rechaza préstamos que superen la caja del cobrador. false: solo advierte.
    'bloquear_sin_base' => env('COBROS_BLOQUEAR_SIN_BASE', true),
];
