<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestamos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained()->cascadeOnDelete();
            $table->string('descripcion')->nullable();
            $table->decimal('monto', 12, 2);
            $table->decimal('interes_pct', 5, 2)->default(0);
            $table->decimal('monto_total', 12, 2);
            $table->unsignedInteger('num_cuotas');
            $table->decimal('valor_cuota', 12, 2);
            $table->string('frecuencia', 12); // diario | semanal | quincenal | mensual
            $table->boolean('saltar_domingos')->default(false);
            $table->date('fecha_inicio');
            $table->string('estado', 10)->default('activo'); // activo | pagado | anulado
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'estado']);
        });

        Schema::create('cuotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestamo_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('numero');
            $table->date('fecha');
            $table->decimal('valor', 12, 2);
            $table->decimal('pagado', 12, 2)->default(0);
            $table->string('estado', 10)->default('pendiente'); // pendiente | pagada
            $table->timestamps();

            $table->index(['prestamo_id', 'numero']);
            $table->index(['fecha', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuotas');
        Schema::dropIfExists('prestamos');
    }
};
