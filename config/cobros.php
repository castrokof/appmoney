<?php

return [
    // true: rechaza préstamos que superen la caja del cobrador. false: solo advierte.
    'bloquear_sin_base' => env('COBROS_BLOQUEAR_SIN_BASE', true),
];
