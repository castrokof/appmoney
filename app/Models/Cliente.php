<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'nombres', 'apellidos', 'documento', 'telefono',
        'direccion', 'barrio', 'observacion', 'orden', 'activo',
    ];

    protected $casts = ['activo' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function prestamos()
    {
        return $this->hasMany(Prestamo::class);
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim($this->nombres.' '.$this->apellidos);
    }

    /** Teléfono solo con dígitos, para enlaces tel: y WhatsApp. */
    public function getTelefonoLimpioAttribute(): ?string
    {
        return $this->telefono ? preg_replace('/\D+/', '', $this->telefono) : null;
    }
}
