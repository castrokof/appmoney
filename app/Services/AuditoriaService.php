<?php

namespace App\Services;

use App\Models\Auditoria;

class AuditoriaService
{
    /** Formato $1.234 para los resúmenes. */
    public static function dinero(float|int|string $v): string
    {
        return '$'.number_format((float) $v, 0, '', '');
    }

    /**
     * Registra un evento. El usuario y la IP salen de la sesión actual (null en consola/tests sin login).
     *
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $despues
     */
    public function registrar(
        string $accion,
        string $resumen,
        ?int $prestamoId = null,
        ?string $entidad = null,
        ?int $entidadId = null,
        ?string $motivo = null,
        ?array $antes = null,
        ?array $despues = null,
    ): Auditoria {
        return Auditoria::create([
            'user_id' => auth()->id(),
            'accion' => $accion,
            'resumen' => mb_substr($resumen, 0, 255),
            'prestamo_id' => $prestamoId,
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'motivo' => $motivo,
            'antes' => $antes,
            'despues' => $despues,
            'ip' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
