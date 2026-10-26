<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gasto extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'monto', 'descripcion', 'fecha'];

    protected $casts = ['monto' => 'decimal:2', 'fecha' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
