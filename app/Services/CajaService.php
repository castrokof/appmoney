<?php

namespace App\Services;

use App\Models\Base;
use App\Models\Gasto;
use App\Models\Pago;
use App\Models\Prestamo;

class CajaService
{
    /** Desglose de la caja de un cobrador (todo derivado, nada guardado). */
    public function desglose(int $userId): array
    {
        $base = (float) Base::where('user_id', $userId)->sum('monto');
        $cobrado = (float) Pago::where('user_id', $userId)->sum('valor');
        $prestado = (float) Prestamo::where('user_id', $userId)->where('estado', '!=', 'anulado')->sum('monto');
        $gastos = (float) Gasto::where('user_id', $userId)->sum('monto');

        return [
            'base' => $base,
            'cobrado' => $cobrado,
            'prestado' => $prestado,
            'gastos' => $gastos,
            'caja' => round($base + $cobrado - $prestado - $gastos, 2),
        ];
    }

    public function caja(int $userId): float
    {
        return $this->desglose($userId)['caja'];
    }
}
