<?php

namespace App\Services;

use App\Models\Cuota;
use App\Models\Prestamo;

/**
 * Calificación del cliente (derivada, nunca guardada).
 *
 * Mira las cuotas ya vencidas de sus préstamos no anulados:
 *   puntaje = 100 - 60 * (cuotas atrasadas / cuotas vencidas) - min(40, 2 * días de la cuota más atrasada)
 * Sin cuotas vencidas todavía el cliente es "Nuevo".
 */
class CalificacionService
{
    /** @return array<int, array> indexado por cliente_id */
    public function paraClientes(array $clienteIds): array
    {
        $clienteIds = array_values(array_unique(array_filter($clienteIds)));
        if (! $clienteIds) {
            return [];
        }

        $hoy = today();

        $filas = Cuota::query()
            ->join('prestamos', 'prestamos.id', '=', 'cuotas.prestamo_id')
            ->whereNull('prestamos.deleted_at')
            ->where('prestamos.estado', '!=', 'anulado')
            ->whereIn('prestamos.cliente_id', $clienteIds)
            ->where('cuotas.fecha', '<', $hoy->toDateString())
            ->groupBy('prestamos.cliente_id')
            ->selectRaw("prestamos.cliente_id,
                COUNT(*) as vencidas,
                SUM(CASE WHEN cuotas.estado = 'pendiente' THEN 1 ELSE 0 END) as atrasadas,
                MIN(CASE WHEN cuotas.estado = 'pendiente' THEN cuotas.fecha END) as mas_antigua")
            ->get()->keyBy('cliente_id');

        $pagados = Prestamo::whereIn('cliente_id', $clienteIds)->where('estado', 'pagado')
            ->selectRaw('cliente_id, COUNT(*) as total')->groupBy('cliente_id')->pluck('total', 'cliente_id');

        $resultado = [];
        foreach ($clienteIds as $id) {
            $f = $filas->get($id);
            $vencidas = (int) ($f->vencidas ?? 0);
            $atrasadas = (int) ($f->atrasadas ?? 0);
            $dias = ! empty($f->mas_antigua) ? (int) \Carbon\Carbon::parse($f->mas_antigua)->diffInDays($hoy) : 0;

            $resultado[$id] = $this->nivel($vencidas, $atrasadas, $dias) + [
                'vencidas' => $vencidas,
                'atrasadas' => $atrasadas,
                'dias_max' => $dias,
                'prestamos_pagados' => (int) ($pagados[$id] ?? 0),
            ];
        }

        return $resultado;
    }

    public function paraCliente(int $clienteId): array
    {
        return $this->paraClientes([$clienteId])[$clienteId];
    }

    private function nivel(int $vencidas, int $atrasadas, int $dias): array
    {
        if ($vencidas === 0) {
            return ['puntaje' => null, 'nivel' => 'nuevo', 'etiqueta' => 'Nuevo', 'color' => 'secondary'];
        }

        $puntaje = (int) round(max(0, 100 - 60 * ($atrasadas / $vencidas) - min(40, 2 * $dias)));

        [$nivel, $etiqueta, $color] = match (true) {
            $puntaje >= 85 => ['excelente', 'Excelente', 'success'],
            $puntaje >= 65 => ['buena', 'Buena', 'primary'],
            $puntaje >= 40 => ['regular', 'Regular', 'warning'],
            default => ['mala', 'Mala', 'danger'],
        };

        return compact('puntaje', 'nivel', 'etiqueta', 'color');
    }
}
