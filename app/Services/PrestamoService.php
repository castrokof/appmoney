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
     * Crea el préstamo y sus cuotas en una transacción.
     * El monto del préstamo es valor_cuota × num_cuotas.
     * $data: user_id, cliente_id, valor_cuota, num_cuotas, frecuencia,
     *        saltar_domingos?, saltar_festivos?, fecha_inicio
     */
    public function crear(array $data): Prestamo
    {
        $n = (int) $data['num_cuotas'];
        $valorCuota = (float) $data['valor_cuota'];
        $monto = $this->total($valorCuota, $n);

        if (config('cobros.usar_base') && config('cobros.bloquear_sin_base')) {
            $disponible = $this->caja->caja((int) $data['user_id']);

            if ($monto > $disponible) {
                throw ValidationException::withMessages([
                    'valor_cuota' => 'Base insuficiente: disponible $'.number_format($disponible, 0, ',', '.'),
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

    /**
     * Renueva un préstamo (pagado o con cuotas pendientes). El saldo pendiente del anterior se cancela
     * con el nuevo préstamo y al cliente se le entrega la diferencia (nuevo monto − saldo).
     * $data: valor_cuota, num_cuotas, fecha_inicio. Hereda cliente, frecuencia y opciones de fechas.
     *
     * @return array{prestamo: Prestamo, saldo: float, entregar: float}
     */
    public function renovar(Prestamo $anterior, array $data, int $userId): array
    {
        return DB::transaction(function () use ($anterior, $data, $userId) {
            $anterior = Prestamo::lockForUpdate()->findOrFail($anterior->id);

            if (! in_array($anterior->estado, ['activo', 'pagado'], true)) {
                throw ValidationException::withMessages(['valor_cuota' => 'Este préstamo no se puede renovar.']);
            }

            $saldo = round((float) $anterior->cuotas()->sum(DB::raw('valor - pagado')), 2);
            $nuevoMonto = $this->total((float) $data['valor_cuota'], (int) $data['num_cuotas']);

            if ($nuevoMonto < $saldo) {
                throw ValidationException::withMessages([
                    'valor_cuota' => 'El nuevo préstamo ($'.number_format($nuevoMonto, 0, ',', '.').') debe cubrir el saldo pendiente ($'.number_format($saldo, 0, ',', '.').').',
                ]);
            }

            if ($saldo > 0) {
                app(PagoService::class)->aplicar(
                    $anterior, $saldo, today()->toDateString(), $userId,
                    'Saldo cancelado por renovación', renovacion: true,
                );
            }

            $nuevo = $this->crear($data + [
                'user_id' => $anterior->user_id,
                'cliente_id' => $anterior->cliente_id,
                'frecuencia' => $anterior->frecuencia,
                'saltar_domingos' => $anterior->saltar_domingos,
                'saltar_festivos' => $anterior->saltar_festivos,
            ]);
            $nuevo->update(['renovado_de_id' => $anterior->id]);

            return ['prestamo' => $nuevo, 'saldo' => $saldo, 'entregar' => round($nuevoMonto - $saldo, 2)];
        });
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
