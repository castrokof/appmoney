<?php

namespace App\Services;

use App\Models\Pago;
use App\Models\Prestamo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PagoService
{
    /** Registra un pago y lo aplica en cascada a las cuotas más antiguas. */
    public function aplicar(Prestamo $prestamo, float $valor, string $fecha, int $userId, ?string $nota = null): Pago
    {
        return DB::transaction(function () use ($prestamo, $valor, $fecha, $userId, $nota) {
            $prestamo = Prestamo::lockForUpdate()->findOrFail($prestamo->id);

            if ($prestamo->estado !== 'activo') {
                throw ValidationException::withMessages(['valor' => 'El préstamo no está activo.']);
            }

            $valor = round($valor, 2);
            $saldo = round((float) $prestamo->cuotas()->sum(DB::raw('valor - pagado')), 2);

            if ($valor <= 0) {
                throw ValidationException::withMessages(['valor' => 'El valor debe ser mayor a cero.']);
            }
            if ($valor > $saldo) {
                throw ValidationException::withMessages([
                    'valor' => 'El pago excede el saldo ($'.number_format($saldo, 0, ',', '.').').',
                ]);
            }

            $primera = $this->distribuir($prestamo, $valor);

            $pago = Pago::create([
                'prestamo_id' => $prestamo->id,
                'cuota_id' => $primera,
                'user_id' => $userId,
                'valor' => $valor,
                'fecha' => $fecha,
                'nota' => $nota,
            ]);

            $this->actualizarEstado($prestamo);

            return $pago;
        });
    }

    /** Borra el pago y reconstruye las cuotas con los pagos restantes. */
    public function deshacer(Pago $pago): void
    {
        DB::transaction(function () use ($pago) {
            $prestamo = Prestamo::withTrashed()->findOrFail($pago->prestamo_id);
            $pago->delete();
            $this->reconstruir($prestamo);
        });
    }

    public function reconstruir(Prestamo $prestamo): void
    {
        \App\Models\Cuota::where('prestamo_id', $prestamo->id)->update(['pagado' => 0, 'estado' => 'pendiente']);

        foreach ($prestamo->pagos()->orderBy('fecha')->orderBy('id')->get() as $p) {
            $this->distribuir($prestamo, (float) $p->valor);
        }

        $this->actualizarEstado($prestamo);
    }

    /** Aplica $valor a las cuotas pendientes de la más antigua a la más nueva. Devuelve la 1ª cuota tocada. */
    private function distribuir(Prestamo $prestamo, float $valor): ?int
    {
        $restante = $valor;
        $primera = null;

        foreach ($prestamo->cuotas()->where('estado', 'pendiente')->get() as $cuota) {
            $falta = round((float) $cuota->valor - (float) $cuota->pagado, 2);
            $aplica = min($restante, $falta);

            $cuota->pagado = round((float) $cuota->pagado + $aplica, 2);
            if ($cuota->pagado >= (float) $cuota->valor) {
                $cuota->estado = 'pagada';
            }
            $cuota->save();

            $primera ??= $cuota->id;
            $restante = round($restante - $aplica, 2);
            if ($restante <= 0) {
                break;
            }
        }

        return $primera;
    }

    private function actualizarEstado(Prestamo $prestamo): void
    {
        if ($prestamo->estado === 'anulado') {
            return;
        }

        $pendientes = $prestamo->cuotas()->where('estado', 'pendiente')->exists();
        $prestamo->update(['estado' => $pendientes ? 'activo' : 'pagado']);
    }
}
