<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\ConsentController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

// Públicas: llegan de Twilio/SendGrid, no de un usuario autenticado.
// La autenticidad se valida con la firma del proveedor, no con Sanctum.
Route::post('/webhooks/{channel}', [WebhookController::class, 'handle'])->name('webhooks.handle');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::post('/contacts/import', [ContactController::class, 'import']);
    Route::apiResource('contacts', ContactController::class)->except(['show']);

    Route::get('/contacts/{contact}/consents', [ConsentController::class, 'index']);
    Route::post('/contacts/{contact}/consents', [ConsentController::class, 'store']);
    Route::patch('/contacts/{contact}/consents/{consent}/revoke', [ConsentController::class, 'revoke']);

    Route::post('/templates/preview', [TemplateController::class, 'preview']);
    Route::apiResource('templates', TemplateController::class)->except(['show']);

    Route::post('/campaigns/{campaign}/dispatch', [CampaignController::class, 'dispatch']);
    Route::apiResource('campaigns', CampaignController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
});
