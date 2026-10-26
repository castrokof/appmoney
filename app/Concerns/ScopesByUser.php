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
}
