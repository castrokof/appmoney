<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Registro de auditoría: solo se crea, nunca se edita ni se borra. */
class Auditoria extends Model
{
    public $timestamps = false;

    protected $table = 'auditorias';

    protected $fillable = [
        'user_id', 'accion', 'resumen', 'prestamo_id', 'entidad', 'entidad_id', 'motivo', 'antes', 'despues', 'ip', 'created_at',
    ];

    protected $casts = ['antes' => 'array', 'despues' => 'array', 'created_at' => 'datetime'];

    /** Acción => [etiqueta, color, ícono] */
    public const ACCIONES = [
        'prestamo.creado' => ['Préstamo creado', 'success', 'bi-plus-circle'],
        'prestamo.renovado' => ['Préstamo renovado', 'primary', 'bi-arrow-repeat'],
        'prestamo.anulado' => ['Préstamo anulado', 'danger', 'bi-x-octagon'],
        'pago.registrado' => ['Pago registrado', 'success', 'bi-cash-stack'],
        'pago.deshecho' => ['Pago deshecho', 'danger', 'bi-arrow-counterclockwise'],
        'cuota.fecha_movida' => ['Fecha de cuota movida', 'warning', 'bi-calendar2-event'],
        'gasto.editado' => ['Gasto editado', 'warning', 'bi-pencil'],
        'gasto.eliminado' => ['Gasto eliminado', 'danger', 'bi-trash'],
        'cliente.eliminado' => ['Cliente eliminado', 'danger', 'bi-person-x'],
        'usuario.creado' => ['Usuario creado', 'success', 'bi-person-plus'],
        'usuario.editado' => ['Usuario editado', 'warning', 'bi-person-gear'],
        'usuario.desactivado' => ['Usuario desactivado', 'danger', 'bi-person-dash'],
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los registros de auditoría no se pueden modificar.'));
        static::deleting(fn () => throw new LogicException('Los registros de auditoría no se pueden borrar.'));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getEtiquetaAttribute(): array
    {
        return self::ACCIONES[$this->accion] ?? [$this->accion, 'secondary', 'bi-dot'];
    }
}
