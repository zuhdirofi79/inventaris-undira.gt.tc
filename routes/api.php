<?php

use App\Http\Controllers\Api\AuthController as ApiAuthController;
use App\Http\Controllers\Api\DashboardController as ApiDashboardController;
use App\Http\Controllers\Api\InventoryController as ApiInventoryController;
use App\Http\Controllers\Api\LocationController as ApiLocationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/login', [ApiAuthController::class, 'login']);

    Route::middleware('api.token')->group(function () {
        Route::post('/logout', [ApiAuthController::class, 'logout']);
        Route::get('/me', [ApiAuthController::class, 'me']);

        Route::get('/dashboard/stats', [ApiDashboardController::class, 'stats']);
        Route::get('/dashboard/recent-items', [ApiDashboardController::class, 'recentItems']);
        Route::get('/dashboard/recent-activity', [ApiDashboardController::class, 'recentActivity']);

        Route::get('/campuses', [ApiLocationController::class, 'campuses']);
        Route::get('/campuses/{campus}/buildings', [ApiLocationController::class, 'buildings']);
        Route::get('/buildings/{building}/floors', [ApiLocationController::class, 'floors']);
        Route::get('/floors/{floor}/rooms', [ApiLocationController::class, 'rooms']);
        Route::get('/rooms/{room}', [ApiLocationController::class, 'roomDetail']);

        Route::get('/inventory/scan/{inventoryNumber}', [ApiInventoryController::class, 'scan']);
        Route::apiResource('inventory', ApiInventoryController::class)
            ->parameters(['inventory' => 'inventoryItem']);
    });
});
