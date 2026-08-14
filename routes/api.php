<?php

use App\Modules\Entity\Controllers\EntityCreateController;
use App\Modules\Entity\Controllers\EntityDetailController;
use App\Modules\Entity\Controllers\EntityReviewController;
use App\Modules\Entity\Controllers\EntitySearchController;
use App\Modules\Relationship\Controllers\RelationshipCreateController;
use App\Modules\Relationship\Controllers\RelationshipReviewController;
use App\Modules\Entity\Controllers\EntityUpdateController;
use App\Modules\Relationship\Controllers\RelationshipUpdateController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Modules\Graph\Controllers\NetworkExploreController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['auth:sanctum', 'throttle:api']);

Route::middleware(['auth:sanctum', 'tenant.context'])->group(function () {

    // Endpoint pencarian — rawan scraping/enumeration, limiter khusus
    Route::get('/entities/search', [EntitySearchController::class, 'search'])
        ->middleware('throttle:search');

    Route::get('/entities/{id}', [EntityDetailController::class, 'show'])
        ->middleware('throttle:api');

    // Graph traversal — query paling berat, limiter paling ketat
    Route::get('/entities/{id}/network', [NetworkExploreController::class, 'explore'])
        ->middleware('throttle:graph');

    Route::middleware(['role:RESEARCHER|TENANT_ADMIN|SUPER_ADMIN', 'throttle:api'])->group(function () {
        Route::post('/entities', [EntityCreateController::class, 'store']);
        Route::patch('/entities/{id}', [EntityUpdateController::class, 'update']);
        Route::patch('/entities/{id}/submit-for-review', [EntityReviewController::class, 'submitForReview']);
        Route::post('/relationships', [RelationshipCreateController::class, 'store']);
        Route::patch('/relationships/{id}', [RelationshipUpdateController::class, 'update']);
        Route::patch('/relationships/{id}/submit-for-review', [RelationshipReviewController::class, 'submitForReview']);
    });

    Route::middleware(['role:LEGAL_REVIEWER|SUPER_ADMIN', 'throttle:api'])->group(function () {
        Route::patch('/entities/{id}/publish', [EntityReviewController::class, 'publish']);
        Route::patch('/entities/{id}/request-revision', [EntityReviewController::class, 'requestRevision']);
        Route::patch('/relationships/{id}/publish', [RelationshipReviewController::class, 'publish']);
        Route::patch('/relationships/{id}/request-revision', [RelationshipReviewController::class, 'requestRevision']);
    });
});