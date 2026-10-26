<?php

use App\Http\Controllers\Api\TicketApiController;
use App\Http\Controllers\Api\TokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| API JSON v1 (prefijo /api). Autenticación con token Bearer de Laravel Sanctum.
| Documentación: docs/API.md
*/
Route::prefix('v1')->name('api.')->group(function () {
    Route::post('/token', [TokenController::class, 'store'])->middleware('throttle:api-login')->name('token.store');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::delete('/token', [TokenController::class, 'destroy'])->name('token.destroy');
        Route::get('/yo', fn (Request $request) => [
            'nombre' => $request->user()->nombre,
            'email' => $request->user()->email,
            'rol' => $request->user()->rolPrincipal()?->value,
            'organizacion' => $request->user()->organizacion?->razon_social,
        ])->name('yo');

        Route::get('/tickets', [TicketApiController::class, 'index'])->name('tickets.index');
        Route::post('/tickets', [TicketApiController::class, 'store'])->name('tickets.store');
        Route::get('/tickets/{ticket}', [TicketApiController::class, 'show'])->name('tickets.show');
    });
});
