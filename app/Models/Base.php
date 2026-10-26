<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Base extends Model
{
    protected $table = 'bases';

    protected $fillable = ['user_id', 'monto', 'fecha', 'nota', 'creado_por'];

    protected $casts = ['monto' => 'decimal:2', 'fecha' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }
}
