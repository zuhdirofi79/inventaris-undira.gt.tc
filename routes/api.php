<?php

use App\Http\Controllers\Api\AuthController as ApiAuthController;
use App\Http\Controllers\Api\DashboardController as ApiDashboardController;
use App\Http\Controllers\Api\InventoryController as ApiInventoryController;
use App\Http\Controllers\Api\LocationController as ApiLocationController;
use App\Http\Controllers\InventorySelectorController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/login', [ApiAuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('api.token')->group(function () {
        Route::post('/logout', [ApiAuthController::class, 'logout']);
        Route::get('/me', [ApiAuthController::class, 'me']);

        // Mirror web: dashboard remains reachable for any authenticated user.
        Route::get('/dashboard/stats', [ApiDashboardController::class, 'stats']);
        Route::get('/dashboard/recent-items', [ApiDashboardController::class, 'recentItems']);
        Route::get('/dashboard/recent-activity', [ApiDashboardController::class, 'recentActivity']);

        Route::middleware('permission:location.view')->group(function () {
            Route::get('/campuses', [ApiLocationController::class, 'campuses']);
            Route::get('/campuses/{campus}/buildings', [ApiLocationController::class, 'buildings']);
            Route::get('/buildings/{building}/floors', [ApiLocationController::class, 'floors']);
            Route::get('/floors/{floor}/rooms', [ApiLocationController::class, 'rooms']);
            Route::get('/rooms/{room}', [ApiLocationController::class, 'roomDetail']);
        });

        Route::middleware('permission:inventory.view')->group(function () {
            Route::get('/inventory/scan/{inventoryNumber}', [ApiInventoryController::class, 'scan'])
                ->name('api.inventory.scan');
            Route::get('/inventory/search', [InventorySelectorController::class, 'search'])
                ->name('api.inventory.search');
            Route::get('/inventory', [ApiInventoryController::class, 'index'])
                ->name('api.inventory.index');
            Route::get('/inventory/{inventoryItem}', [ApiInventoryController::class, 'show'])
                ->name('api.inventory.show');
        });

        Route::post('/inventory', [ApiInventoryController::class, 'store'])
            ->middleware('permission:inventory.create')
            ->name('api.inventory.store');

        Route::match(['put', 'patch'], '/inventory/{inventoryItem}', [ApiInventoryController::class, 'update'])
            ->middleware('permission:inventory.update')
            ->name('api.inventory.update');

        Route::delete('/inventory/{inventoryItem}', [ApiInventoryController::class, 'destroy'])
            ->middleware('permission:inventory.delete')
            ->name('api.inventory.destroy');
    });
});
