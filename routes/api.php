<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\PermissionTreeController;
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

    // Panel de cliente. Cada acción exige su permiso de config/permissions.php;
    // el tenant de la plataforma también lo usa para sus propias campañas.
    Route::post('/contacts/import', [ContactController::class, 'import'])->middleware('permission:contacts.import');
    Route::apiResource('contacts', ContactController::class)->except(['show'])
        ->middlewareFor('index', 'permission:contacts.view')
        ->middlewareFor('store', 'permission:contacts.create')
        ->middlewareFor('update', 'permission:contacts.update')
        ->middlewareFor('destroy', 'permission:contacts.delete');

    Route::get('/contacts/{contact}/consents', [ConsentController::class, 'index'])->middleware('permission:contacts.view');
    Route::post('/contacts/{contact}/consents', [ConsentController::class, 'store'])->middleware('permission:contacts.consents');
    Route::patch('/contacts/{contact}/consents/{consent}/revoke', [ConsentController::class, 'revoke'])->middleware('permission:contacts.consents');

    Route::post('/templates/preview', [TemplateController::class, 'preview'])->middleware('permission:templates.view');
    Route::apiResource('templates', TemplateController::class)->except(['show'])
        ->middlewareFor('index', 'permission:templates.view')
        ->middlewareFor('store', 'permission:templates.create')
        ->middlewareFor('update', 'permission:templates.update')
        ->middlewareFor('destroy', 'permission:templates.delete');

    Route::post('/campaigns/{campaign}/dispatch', [CampaignController::class, 'dispatch'])->middleware('permission:campaigns.dispatch');
    Route::apiResource('campaigns', CampaignController::class)->only(['index', 'store', 'show', 'update', 'destroy'])
        ->middlewareFor(['index', 'show'], 'permission:campaigns.view')
        ->middlewareFor('store', 'permission:campaigns.create')
        ->middlewareFor('update', 'permission:campaigns.update')
        ->middlewareFor('destroy', 'permission:campaigns.delete');

    // Administración de la plataforma: solo el tenant de AlertPrompt.
    Route::prefix('admin')->middleware('platform')->group(function () {
        Route::get('/permissions', PermissionTreeController::class)->middleware('permission:admin.roles.view');
    });
});
