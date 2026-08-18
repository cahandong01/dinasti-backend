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
use App\Modules\Graph\Controllers\FindConnectionController;
use App\Modules\TenantRegion\Controllers\AuthController;
use App\Modules\TenantRegion\Controllers\InviteController;
use App\Modules\Dispute\Controllers\DisputeController;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');
Route::post('/invites/{token}/accept', [InviteController::class, 'accept'])->middleware('throttle:auth');
Route::post('/disputes', [DisputeController::class, 'store'])->middleware('throttle:dispute');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['auth:sanctum', 'throttle:api']);

Route::post('/logout', [AuthController::class, 'logout'])->middleware(['auth:sanctum', 'throttle:api']);

Route::middleware(['auth:sanctum', 'tenant.context'])->group(function () {

    Route::get('/entities/search', [EntitySearchController::class, 'search'])
        ->middleware('throttle:search');

        Route::get('/entities/{slug}', [EntityDetailController::class, 'show'])
        ->middleware('throttle:api');

    Route::get('/entities/{id}/network', [NetworkExploreController::class, 'explore'])
        ->middleware('throttle:graph');

    Route::get('/entities/{id}/find-connection', [FindConnectionController::class, 'find'])
        ->middleware('throttle:graph');

    Route::middleware(['has_role:RESEARCHER|TENANT_ADMIN|SUPER_ADMIN', 'throttle:api'])->group(function () {
        Route::post('/entities', [EntityCreateController::class, 'store']);
        Route::patch('/entities/{id}', [EntityUpdateController::class, 'update']);
        Route::patch('/entities/{id}/submit-for-review', [EntityReviewController::class, 'submitForReview']);
        Route::post('/relationships', [RelationshipCreateController::class, 'store']);
        Route::patch('/relationships/{id}', [RelationshipUpdateController::class, 'update']);
        Route::patch('/relationships/{id}/submit-for-review', [RelationshipReviewController::class, 'submitForReview']);
    });

    Route::middleware(['has_role:LEGAL_REVIEWER|SUPER_ADMIN', 'throttle:api'])->group(function () {
        Route::patch('/entities/{id}/publish', [EntityReviewController::class, 'publish']);
        Route::patch('/entities/{id}/request-revision', [EntityReviewController::class, 'requestRevision']);
        Route::patch('/relationships/{id}/publish', [RelationshipReviewController::class, 'publish']);
        Route::patch('/relationships/{id}/request-revision', [RelationshipReviewController::class, 'requestRevision']);
        Route::patch('/disputes/{id}/approve', [DisputeController::class, 'approve']);
        Route::patch('/disputes/{id}/reject', [DisputeController::class, 'reject']);
    });

    Route::middleware(['has_role:TENANT_ADMIN|SUPER_ADMIN', 'throttle:api'])->group(function () {
        Route::post('/invites', [InviteController::class, 'store']);
    });

    Route::middleware(['has_role:SUPER_ADMIN', 'throttle:api'])->group(function () {
        Route::patch('/invites/{id}/approve', [InviteController::class, 'approve']);
        Route::patch('/invites/{id}/reject', [InviteController::class, 'reject']);
    });
});