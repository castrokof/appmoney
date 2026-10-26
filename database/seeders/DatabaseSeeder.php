<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Primer usuario administrador. Cambiar la clave apenas se entre.
        User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@cobros.test')],
            [
                'name' => 'Administrador',
                'password' => env('ADMIN_PASSWORD', 'cambiar-esta-clave'),
                'role' => 'admin',
                'activo' => true,
            ]
        );
    }
}
