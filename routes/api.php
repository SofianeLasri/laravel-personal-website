<?php

use App\Http\Controllers\TranslationController;

Route::name('api.')->prefix('api')->group(function () {
    /*Route::resource('translations', TranslationController::class);*/
    Route::name('translation.')->prefix('translations')->group(function () {
        Route::get('/', [TranslationController::class, 'index'])->name('index');
        Route::post('/', [TranslationController::class, 'store'])->name('store');
        Route::get('/{key}/{locale}', [TranslationController::class, 'show'])->name('show');
        Route::put('/', [TranslationController::class, 'update'])->name('update');
        Route::delete('/{key}', [TranslationController::class, 'destroy'])->name('destroy');
    });
});