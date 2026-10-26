<?php

namespace App\Services;

use App\Models\Prestamo;
use App\Support\FestivosColombia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrestamoService
{
    public function __construct(private CajaService $caja)
    {
    }

    /** Total del préstamo = valor de la cuota × número de cuotas. */
    public function total(float $valorCuota, int $numCuotas): float
    {
        return round($valorCuota * $numCuotas, 2);
    }

    /**
     * Crea el préstamo y sus cuotas en una transacción. Dos formas de calcular:
     *  - por cuota:       se envía valor_cuota → monto = valor_cuota × num_cuotas
     *  - por monto total: se envía monto_total → valor_cuota = monto_total ÷ num_cuotas
     *                     (la última cuota ajusta la diferencia de redondeo)
     * $data: user_id, cliente_id, (valor_cuota | monto_total), num_cuotas, frecuencia,
     *        saltar_domingos?, saltar_festivos?, fecha_inicio
     */
    public function crear(array $data): Prestamo
    {
        $n = (int) $data['num_cuotas'];

        if (! empty($data['monto_total'])) {
            $monto = round((float) $data['monto_total'], 2);
            $valorCuota = round($monto / max($n, 1), 0);
        } else {
            $valorCuota = (float) $data['valor_cuota'];
            $monto = $this->total($valorCuota, $n);
        }

        if (config('cobros.usar_base') && config('cobros.bloquear_sin_base')) {
            $disponible = $this->caja->caja((int) $data['user_id']);

            if ($monto > $disponible) {
                throw ValidationException::withMessages([
                    'monto_total' => 'Base insuficiente: disponible $'.number_format($disponible, 0, ',', '.'),
                ]);
            }
        }

        return DB::transaction(function () use ($data, $monto, $valorCuota, $n) {
            $diario = $data['frecuencia'] === 'diario';

            $prestamo = Prestamo::create([
                'user_id' => $data['user_id'],
                'cliente_id' => $data['cliente_id'],
                'monto' => $monto,
                'interes_pct' => 0, // ya no se usa
                'monto_total' => $monto,
                'num_cuotas' => $n,
                'valor_cuota' => $valorCuota,
                'frecuencia' => $data['frecuencia'],
                // Saltar domingos/festivos solo aplica a préstamos diarios
                'saltar_domingos' => $diario && ! empty($data['saltar_domingos']),
                'saltar_festivos' => $diario && ! empty($data['saltar_festivos']),
                'fecha_inicio' => $data['fecha_inicio'],
                'estado' => 'activo',
            ]);

            $this->generarCuotas($prestamo);

            return $prestamo;
        });
    }

    public function generarCuotas(Prestamo $p): void
    {
        $fecha = $this->ajustar(Carbon::parse($p->fecha_inicio)->startOfDay(), $p);

        $acumulado = 0.0;
        $filas = [];
        for ($i = 1; $i <= $p->num_cuotas; $i++) {
            // La última cuota ajusta el redondeo para que la suma sea exactamente el monto
            $valor = $i === (int) $p->num_cuotas
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

            $fecha = $this->ajustar(match ($p->frecuencia) {
                'diario' => $fecha->copy()->addDay(),
                'semanal' => $fecha->copy()->addDays(7),
                'quincenal' => $fecha->copy()->addDays(15),
                'mensual' => $fecha->copy()->addMonthNoOverflow(),
            }, $p);
        }

        $p->cuotas()->insert($filas);
    }

    /** Avanza la fecha hasta un día hábil según las opciones del préstamo (solo diarios). */
    private function ajustar(Carbon $fecha, Prestamo $p): Carbon
    {
        if ($p->frecuencia !== 'diario') {
            return $fecha;
        }

        while (($p->saltar_domingos && $fecha->isSunday())
            || ($p->saltar_festivos && FestivosColombia::esFestivo($fecha))) {
            $fecha->addDay();
        }

        return $fecha;
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
