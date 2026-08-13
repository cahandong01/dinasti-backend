<?php

use App\Modules\Entity\Controllers\EntityCreateController;
use App\Modules\Entity\Controllers\EntityDetailController;
use App\Modules\Entity\Controllers\EntitySearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['auth:sanctum', 'tenant.context'])->group(function () {
    Route::get('/entities/search', [EntitySearchController::class, 'search']);
    Route::get('/entities/{id}', [EntityDetailController::class, 'show']);

    Route::middleware(['role:RESEARCHER|TENANT_ADMIN|SUPER_ADMIN'])->group(function () {
        Route::post('/entities', [EntityCreateController::class, 'store']);
    });
});