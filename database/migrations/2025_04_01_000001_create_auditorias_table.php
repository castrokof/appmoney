<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sin llaves foráneas a propósito: la auditoría debe sobrevivir aunque se borre lo auditado.
        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('accion', 40);
            $table->string('resumen', 255);
            $table->unsignedBigInteger('prestamo_id')->nullable();
            $table->string('entidad', 30)->nullable();
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->text('motivo')->nullable();
            $table->json('antes')->nullable();
            $table->json('despues')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('prestamo_id');
            $table->index(['user_id', 'created_at']);
            $table->index('accion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};
