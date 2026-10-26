<?php

use App\Http\Controllers\BaseController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CobroController;
use App\Http\Controllers\GastoController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PrestamoController;
use App\Http\Controllers\ResumenController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/', [CobroController::class, 'hoy'])->name('hoy');
    Route::get('/atrasados', [CobroController::class, 'atrasados'])->name('atrasados');

    Route::resource('clientes', ClienteController::class)->except('show');

    Route::resource('prestamos', PrestamoController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('prestamos/{prestamo}/renovar', [PrestamoController::class, 'renovar'])->name('prestamos.renovar');
    Route::post('prestamos/{prestamo}/anular', [PrestamoController::class, 'anular'])->name('prestamos.anular');
    Route::patch('cuotas/{cuota}/fecha', [PrestamoController::class, 'moverCuota'])->name('cuotas.fecha');

    Route::post('prestamos/{prestamo}/pagos', [PagoController::class, 'store'])->name('pagos.store');
    Route::delete('pagos/{pago}', [PagoController::class, 'destroy'])->name('pagos.destroy');

    Route::resource('gastos', GastoController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::middleware('role:admin,empresa')->group(function () {
        Route::get('/bases', [BaseController::class, 'index'])->name('bases.index');
        Route::post('/bases', [BaseController::class, 'store'])->name('bases.store');
        Route::get('/resumen', [ResumenController::class, 'index'])->name('resumen');
        Route::resource('usuarios', UsuarioController::class)->except('show');
    });
});

require __DIR__.'/auth.php';
