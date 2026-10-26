<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cuota extends Model
{
    protected $fillable = ['prestamo_id', 'numero', 'fecha', 'valor', 'pagado', 'estado'];

    protected $casts = [
        'fecha' => 'date',
        'valor' => 'decimal:2',
        'pagado' => 'decimal:2',
    ];

    public function prestamo()
    {
        return $this->belongsTo(Prestamo::class);
    }

    public function getFaltaAttribute(): float
    {
        return round((float) $this->valor - (float) $this->pagado, 2);
    }

    public function getAtrasadaAttribute(): bool
    {
        return $this->estado === 'pendiente' && $this->fecha->lt(today());
    }

    /** pagada | parcial | atrasada | pendiente (para colores) */
    public function getEstadoVisualAttribute(): string
    {
        if ($this->estado === 'pagada') {
            return 'pagada';
        }
        if ($this->atrasada) {
            return 'atrasada';
        }

        return (float) $this->pagado > 0 ? 'parcial' : 'pendiente';
    }
}
