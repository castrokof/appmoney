<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prestamo extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'cliente_id', 'monto', 'interes_pct', 'monto_total',
        'num_cuotas', 'valor_cuota', 'frecuencia', 'saltar_domingos', 'saltar_festivos', 'fecha_inicio', 'estado',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'interes_pct' => 'decimal:2',
        'monto_total' => 'decimal:2',
        'valor_cuota' => 'decimal:2',
        'saltar_domingos' => 'boolean',
        'saltar_festivos' => 'boolean',
        'fecha_inicio' => 'date',
    ];

    public const FRECUENCIAS = ['diario', 'semanal', 'quincenal', 'mensual'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }

    public function cuotas()
    {
        return $this->hasMany(Cuota::class)->orderBy('numero');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class);
    }

    /**
     * Agrega saldo, atrasado, cuotas atrasadas y por cobrar hoy como subconsultas
     * (valores derivados, nunca guardados). Evita N+1 en los listados.
     */
    public function scopeConTotales(Builder $q): Builder
    {
        $hoy = today()->toDateString();
        $base = fn () => Cuota::query()->whereColumn('cuotas.prestamo_id', 'prestamos.id');

        return $q->addSelect([
            'saldo_calc' => $base()->selectRaw('COALESCE(SUM(valor - pagado), 0)'),
            'atrasado_calc' => $base()->selectRaw('COALESCE(SUM(valor - pagado), 0)')
                ->where('estado', 'pendiente')->where('fecha', '<', $hoy),
            'cuotas_atrasadas_calc' => $base()->selectRaw('COUNT(*)')
                ->where('estado', 'pendiente')->where('fecha', '<', $hoy),
            // Cuota en la que va: la primera pendiente (null si ya pagó todas)
            'cuota_actual_calc' => $base()->selectRaw('MIN(numero)')->where('estado', 'pendiente'),
            'hoy_calc' => $base()->selectRaw('COALESCE(SUM(valor - pagado), 0)')
                ->where('estado', 'pendiente')->where('fecha', '<=', $hoy),
        ]);
    }

    /** Número de la cuota en la que va (la próxima por pagar) o null si ya está al día completo. */
    public function getCuotaActualAttribute(): ?int
    {
        $n = array_key_exists('cuota_actual_calc', $this->attributes)
            ? $this->attributes['cuota_actual_calc']
            : $this->cuotas()->where('estado', 'pendiente')->min('numero');

        return $n === null ? null : (int) $n;
    }

    public function getSaldoAttribute(): float
    {
        return (float) ($this->attributes['saldo_calc']
            ?? $this->cuotas()->sum(\DB::raw('valor - pagado')));
    }

    public function getAtrasadoAttribute(): float
    {
        return (float) ($this->attributes['atrasado_calc']
            ?? $this->cuotas()->where('estado', 'pendiente')->where('fecha', '<', today())->sum(\DB::raw('valor - pagado')));
    }

    public function getCuotasAtrasadasAttribute(): int
    {
        return (int) ($this->attributes['cuotas_atrasadas_calc']
            ?? $this->cuotas()->where('estado', 'pendiente')->where('fecha', '<', today())->count());
    }

    /** Lo que toca cobrar hoy (incluye atrasadas). */
    public function getPorCobrarHoyAttribute(): float
    {
        return (float) ($this->attributes['hoy_calc']
            ?? $this->cuotas()->where('estado', 'pendiente')->where('fecha', '<=', today())->sum(\DB::raw('valor - pagado')));
    }
}
