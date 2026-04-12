<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\Geo\GeoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PGA Open — API Routes
|--------------------------------------------------------------------------
| Toutes les routes passent par le middleware TenantResolver qui configure
| la connexion DB et la config PGA du tenant courant.
*/

// ── Config publique (pas d'auth) ────────────────────────────────────────────
Route::get('/v1/config', ConfigController::class);

// ── Auth ─────────────────────────────────────────────────────────────────────
Route::prefix('v1/auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/password', [AuthController::class, 'changePassword']);
    });
});

// ── Routes authentifi\u00e9es ────────────────────────────────────────────────────
Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {

    // G\u00e9ographie
    Route::prefix('geo')->group(function () {
        Route::get('/levels', [GeoController::class, 'levels']);
        Route::get('/units', [GeoController::class, 'units']);
        Route::get('/units/{id}/children', [GeoController::class, 'children']);
        Route::get('/units/{id}/ancestors', [GeoController::class, 'ancestors']);
    });

    // TODO Phase 4+ : Agent CRUD, Statuts, Paiements, Rapports, Admin
    // Route::prefix('agents')->group(function () { ... });
    // Route::prefix('functionality')->group(function () { ... });
    // Route::prefix('payments')->group(function () { ... });
    // Route::prefix('reports')->group(function () { ... });
    // Route::prefix('admin')->group(function () { ... });
});
