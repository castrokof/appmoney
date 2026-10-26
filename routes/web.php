<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::view('/', 'cobro.hoy')->name('hoy');
});

require __DIR__.'/auth.php';
