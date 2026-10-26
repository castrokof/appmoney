<?php

namespace App\Concerns;

use App\Models\User;

trait ScopesByUser
{
    /** Ids de usuario cuyos datos puede ver el usuario actual. */
    protected function userIds(): array
    {
        $u = auth()->user();

        if ($u->role === 'admin') {
            return User::pluck('id')->all();
        }

        if ($u->role === 'empresa') {
            return User::where('empresa_id', $u->empresa_id)->pluck('id')->all();
        }

        return [$u->id];
    }

    /** Usuarios dentro del alcance (para selects de cobrador). */
    protected function usuariosVisibles()
    {
        return User::whereIn('id', $this->userIds())->where('activo', true)->orderBy('name')->get();
    }

    /** 404 si el registro pertenece a un usuario fuera del alcance (evita ver datos ajenos por URL). */
    protected function autorizar($modelo, string $campo = 'user_id'): void
    {
        abort_unless(in_array($modelo->{$campo}, $this->userIds(), true), 404);
    }

    /** Valida que un user_id recibido del formulario esté dentro del alcance; si no es gestor, siempre el propio. */
    protected function userIdPermitido(?int $pedido): int
    {
        $u = auth()->user();
        if (! $u->esGestor() || ! $pedido) {
            return $u->id;
        }
        abort_unless(in_array($pedido, $this->userIds(), true), 403);

        return $pedido;
    }
}
