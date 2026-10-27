<?php

namespace App\Services;

use App\Models\Pago;
use App\Models\Prestamo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PagoService
{
    public function __construct(private AuditoriaService $auditoria)
    {
    }

    /** Registra un pago y lo aplica en cascada a las cuotas más antiguas. */
    public function aplicar(Prestamo $prestamo, float $valor, string $fecha, int $userId, ?string $nota = null, bool $renovacion = false): Pago
    {
        return DB::transaction(function () use ($prestamo, $valor, $fecha, $userId, $nota, $renovacion) {
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
                    'valor' => 'El pago excede el saldo ($'.number_format($saldo, 0, '', '').').',
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
                'renovacion' => $renovacion,
            ]);

            $this->actualizarEstado($prestamo);

            if (! $renovacion) { // el pago de renovación ya queda en el evento «préstamo renovado»
                $this->auditoria->registrar('pago.registrado',
                    'Pago de '.AuditoriaService::dinero($valor).' registrado'.($nota ? " ({$nota})" : ''),
                    $prestamo->id, 'pago', $pago->id, null, null,
                    ['valor' => $valor, 'fecha' => $fecha, 'cobro_user_id' => $userId]);
            }

            return $pago;
        });
    }

    /** Borra el pago y reconstruye las cuotas con los pagos restantes. Deja registro con el motivo. */
    public function deshacer(Pago $pago, string $motivo): void
    {
        DB::transaction(function () use ($pago, $motivo) {
            $prestamo = Prestamo::withTrashed()->findOrFail($pago->prestamo_id);
            $antes = ['pago_id' => $pago->id, 'valor' => (float) $pago->valor, 'fecha' => $pago->fecha->toDateString(),
                'nota' => $pago->nota, 'cobro_user_id' => $pago->user_id, 'registrado_el' => $pago->created_at?->toDateTimeString()];
            $saldoAntes = round((float) $prestamo->cuotas()->sum(DB::raw('valor - pagado')), 2);

            // Al deshacer, el saldo vuelve a subir exactamente el valor del pago
            $this->auditoria->registrar('pago.deshecho',
                'Pago de '.AuditoriaService::dinero($pago->valor).' del '.$pago->fecha->format('d/m/Y').' deshecho',
                $prestamo->id, 'pago', $pago->id, $motivo, $antes,
                ['saldo_antes' => $saldoAntes, 'saldo_despues' => round($saldoAntes + (float) $pago->valor, 2)]);

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
