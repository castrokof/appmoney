<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Prestamo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrestamoService
{
    public function __construct(private CajaService $caja)
    {
    }

    /** @return array{monto_total: float, valor_cuota: float} */
    public function calcular(float $monto, float $interesPct, int $numCuotas): array
    {
        $total = round($monto * (1 + $interesPct / 100), 2);

        return [
            'monto_total' => $total,
            'valor_cuota' => round($total / max($numCuotas, 1), 0),
        ];
    }

    /**
     * Crea el préstamo y sus cuotas en una transacción.
     * $data: user_id, cliente_id, descripcion?, monto, interes_pct, num_cuotas,
     *        frecuencia, saltar_domingos?, fecha_inicio
     */
    public function crear(array $data): Prestamo
    {
        $monto = (float) $data['monto'];
        $disponible = $this->caja->caja((int) $data['user_id']);

        if ($monto > $disponible && config('cobros.bloquear_sin_base')) {
            throw ValidationException::withMessages([
                'monto' => 'Base insuficiente: disponible $'.number_format($disponible, 0, ',', '.'),
            ]);
        }

        return DB::transaction(function () use ($data, $monto) {
            $calc = $this->calcular($monto, (float) $data['interes_pct'], (int) $data['num_cuotas']);

            $prestamo = Prestamo::create([
                'user_id' => $data['user_id'],
                'cliente_id' => $data['cliente_id'],
                'descripcion' => $data['descripcion'] ?? null,
                'monto' => $monto,
                'interes_pct' => $data['interes_pct'],
                'monto_total' => $calc['monto_total'],
                'num_cuotas' => $data['num_cuotas'],
                'valor_cuota' => $calc['valor_cuota'],
                'frecuencia' => $data['frecuencia'],
                'saltar_domingos' => (bool) ($data['saltar_domingos'] ?? false),
                'fecha_inicio' => $data['fecha_inicio'],
                'estado' => 'activo',
            ]);

            $this->generarCuotas($prestamo);

            return $prestamo;
        });
    }

    public function generarCuotas(Prestamo $p): void
    {
        $saltar = $p->saltar_domingos && $p->frecuencia === 'diario';
        $fecha = Carbon::parse($p->fecha_inicio)->startOfDay();
        if ($saltar && $fecha->isSunday()) {
            $fecha->addDay();
        }

        $acumulado = 0.0;
        $filas = [];
        for ($i = 1; $i <= $p->num_cuotas; $i++) {
            $valor = $i === $p->num_cuotas
                ? round((float) $p->monto_total - $acumulado, 2)
                : (float) $p->valor_cuota;
            $acumulado += $valor;

            $filas[] = [
                'prestamo_id' => $p->id,
                'numero' => $i,
                'fecha' => $fecha->toDateString(),
                'valor' => $valor,
                'pagado' => 0,
                'estado' => 'pendiente',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $fecha = match ($p->frecuencia) {
                'diario' => $fecha->copy()->addDay(),
                'semanal' => $fecha->copy()->addDays(7),
                'quincenal' => $fecha->copy()->addDays(15),
                'mensual' => $fecha->copy()->addMonthNoOverflow(),
            };
            if ($saltar && $fecha->isSunday()) {
                $fecha->addDay();
            }
        }

        $p->cuotas()->insert($filas);
    }

    /** Anula: estado + softDelete. Su monto vuelve a la caja (deja de sumarse). */
    public function anular(Prestamo $p): void
    {
        DB::transaction(function () use ($p) {
            $p->update(['estado' => 'anulado']);
            $p->delete();
        });
    }
}
