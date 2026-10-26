<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestamo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cuota_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // quien cobró
            $table->decimal('valor', 12, 2);
            $table->date('fecha');
            $table->string('nota')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'fecha']);
        });

        Schema::create('gastos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('monto', 12, 2);
            $table->string('descripcion', 150);
            $table->date('fecha');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'fecha']);
        });

        Schema::create('bases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // cobrador que recibe
            $table->decimal('monto', 12, 2); // negativo = devolución
            $table->date('fecha');
            $table->string('nota')->nullable();
            $table->foreignId('creado_por')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bases');
        Schema::dropIfExists('gastos');
        Schema::dropIfExists('pagos');
    }
};
