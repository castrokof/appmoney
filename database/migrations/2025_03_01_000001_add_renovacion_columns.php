<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prestamos', function (Blueprint $table) {
            $table->unsignedBigInteger('renovado_de_id')->nullable()->after('estado');
        });

        Schema::table('pagos', function (Blueprint $table) {
            // Pago que cancela el saldo de un préstamo al renovarlo: no entra efectivo
            $table->boolean('renovacion')->default(false)->after('nota');
        });
    }

    public function down(): void
    {
        Schema::table('pagos', fn (Blueprint $t) => $t->dropColumn('renovacion'));
        Schema::table('prestamos', fn (Blueprint $t) => $t->dropColumn('renovado_de_id'));
    }
};
