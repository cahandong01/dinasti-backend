<?php

use App\Modules\Entity\Controllers\EntitySearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['auth:sanctum', 'tenant.context'])->group(function () {
    Route::get('/entities/search', [EntitySearchController::class, 'search']);
});