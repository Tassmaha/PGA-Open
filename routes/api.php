<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Agent\AgentController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\Geo\GeoController;
use App\Http\Controllers\Report\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PGA Open \u2014 API Routes v1
|--------------------------------------------------------------------------
| Toutes les routes passent par TenantResolver (middleware global API).
*/

// \u2500\u2500 Config publique (sans auth) \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500
Route::get('/v1/config', ConfigController::class);

// \u2500\u2500 Auth \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500
Route::prefix('v1/auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/password', [AuthController::class, 'changePassword']);
    });
});

// \u2500\u2500 Routes authentifi\u00e9es \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500
Route::middleware(['auth:sanctum', 'zone.access'])->prefix('v1')->group(function () {

    // G\u00e9ographie dynamique
    Route::prefix('geo')->group(function () {
        Route::get('/levels', [GeoController::class, 'levels']);
        Route::get('/units', [GeoController::class, 'units']);
        Route::get('/units/{id}/children', [GeoController::class, 'children']);
        Route::get('/units/{id}/ancestors', [GeoController::class, 'ancestors']);
    });

    // Agents (ex-ASBC)
    Route::prefix('agents')->group(function () {
        Route::get('/', [AgentController::class, 'index']);
        Route::post('/', [AgentController::class, 'store'])->middleware('role:admin_dsc,superviseur_dsc,rps,icp');
        Route::get('/indicateurs', [AgentController::class, 'indicateurs']);
        Route::get('/{agent}', [AgentController::class, 'show']);
        Route::patch('/{agent}', [AgentController::class, 'update'])->middleware('role:admin_dsc,superviseur_dsc,rps,icp');
        Route::post('/{agent}/valider', [AgentController::class, 'valider'])->middleware('role:admin_dsc,superviseur_dsc');
        Route::post('/{agent}/rejeter', [AgentController::class, 'rejeter'])->middleware('role:admin_dsc,superviseur_dsc');
        Route::post('/{agent}/desactiver', [AgentController::class, 'desactiver'])->middleware('role:admin_dsc,superviseur_dsc,rps,icp');
        Route::post('/{agent}/reactiver', [AgentController::class, 'reactiver'])->middleware('role:admin_dsc,superviseur_dsc');
    });

    // Rapports & Dashboard
    Route::prefix('reports')->group(function () {
        Route::get('/dashboard', [ReportController::class, 'dashboard']);
        Route::get('/agents', [ReportController::class, 'listeAgents']);
        Route::get('/id-documents', [ReportController::class, 'idDocuments']);
    });

    // Administration (admin DSC uniquement)
    Route::prefix('admin/users')->middleware('role:admin_dsc,superviseur_dsc')->group(function () {
        Route::get('/', [AdminController::class, 'index']);
        Route::post('/', [AdminController::class, 'store']);
        Route::get('/{utilisateur}', [AdminController::class, 'show']);
        Route::patch('/{utilisateur}', [AdminController::class, 'update']);
        Route::delete('/{utilisateur}', [AdminController::class, 'destroy']);
        Route::post('/{utilisateur}/reset-password', [AdminController::class, 'reinitialiserMotDePasse']);
    });
});
