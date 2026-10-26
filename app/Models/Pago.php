<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    protected $fillable = ['prestamo_id', 'cuota_id', 'user_id', 'valor', 'fecha', 'nota', 'renovacion'];

    protected $casts = ['valor' => 'decimal:2', 'fecha' => 'date', 'renovacion' => 'boolean'];

    public function prestamo()
    {
        return $this->belongsTo(Prestamo::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
