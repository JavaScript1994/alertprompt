<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\ClientController;
use App\Http\Controllers\Api\Admin\ImpersonationController;
use App\Http\Controllers\Api\Admin\ModuleController;
use App\Http\Controllers\Api\Admin\PermissionTreeController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\ConsentController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\TenantSettingsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [PasswordController::class, 'forgot'])->middleware('throttle:5,1');
Route::post('/reset-password', [PasswordController::class, 'reset'])->middleware('throttle:10,1');

// Públicas: llegan de Twilio/SendGrid, no de un usuario autenticado.
// La autenticidad se valida con la firma del proveedor, no con Sanctum.
Route::post('/webhooks/{channel}', [WebhookController::class, 'handle'])->name('webhooks.handle');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Panel de cliente. Cada acción exige su permiso de config/permissions.php;
    // el tenant de la plataforma también lo usa para sus propias campañas.
    Route::post('/contacts/import', [ContactController::class, 'import'])->middleware(['permission:contacts.import', 'module:csv_import']);
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

    Route::post('/campaigns/{campaign}/dispatch', [CampaignController::class, 'dispatch'])->middleware(['permission:campaigns.dispatch', 'not-impersonating']);
    Route::apiResource('campaigns', CampaignController::class)->only(['index', 'store', 'show', 'update', 'destroy'])
        ->middlewareFor(['index', 'show'], 'permission:campaigns.view')
        ->middlewareFor('store', 'permission:campaigns.create')
        ->middlewareFor('update', 'permission:campaigns.update')
        ->middlewareFor('destroy', 'permission:campaigns.delete');

    // Usuarios y configuración de la cuenta (también en modo soporte).
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view');
    Route::get('/users/roles', [UserController::class, 'roles'])->middleware('permission:users.view');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.manage');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.manage');
    Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])->middleware('permission:users.manage');
    Route::post('/users/{user}/reactivate', [UserController::class, 'reactivate'])->middleware('permission:users.manage');
    Route::post('/users/{user}/resend-invitation', [UserController::class, 'resendInvitation'])->middleware('permission:users.manage');

    Route::get('/settings/account', [TenantSettingsController::class, 'show'])->middleware('permission:settings.view');
    Route::put('/settings/account', [TenantSettingsController::class, 'update'])->middleware('permission:settings.manage');

    // Administración de la plataforma: solo el tenant de AlertPrompt.
    Route::prefix('admin')->middleware('platform')->group(function () {
        Route::get('/permissions', PermissionTreeController::class)->middleware('permission:admin.roles.view');

        Route::get('/clients', [ClientController::class, 'index'])->middleware('permission:admin.clients.view');
        Route::post('/clients', [ClientController::class, 'store'])->middleware('permission:admin.clients.create');
        Route::get('/clients/{client}', [ClientController::class, 'show'])->middleware('permission:admin.clients.view');
        Route::put('/clients/{client}', [ClientController::class, 'update'])->middleware('permission:admin.clients.update');
        Route::post('/clients/{client}/suspend', [ClientController::class, 'suspend'])->middleware('permission:admin.clients.suspend');
        Route::post('/clients/{client}/reactivate', [ClientController::class, 'reactivate'])->middleware('permission:admin.clients.suspend');
        Route::get('/clients/{client}/users', [ClientController::class, 'users'])->middleware('permission:admin.clients.view');
        Route::get('/clients/{client}/activity', [ClientController::class, 'activity'])->middleware('permission:admin.clients.view');

        Route::get('/modules', [ModuleController::class, 'index'])->middleware('permission:admin.modules.view');
        Route::get('/clients/{client}/modules', [ModuleController::class, 'show'])->middleware('permission:admin.modules.view');
        Route::put('/clients/{client}/modules', [ModuleController::class, 'update'])->middleware('permission:admin.modules.manage');

        // Supervisión (solo lectura): los mismos listados del panel de cliente
        // con los datos de {client}.
        Route::prefix('/clients/{client}/supervision')->middleware(['permission:admin.supervision.view', 'supervise'])->group(function () {
            Route::get('/contacts', [ContactController::class, 'index']);
            Route::get('/templates', [TemplateController::class, 'index']);
            Route::get('/campaigns', [CampaignController::class, 'index']);
        });

        // Soporte: entrar al panel del cliente (queda auditado).
        Route::post('/clients/{client}/impersonate', [ImpersonationController::class, 'store'])->middleware('permission:admin.impersonate.use');
        Route::delete('/impersonation', [ImpersonationController::class, 'destroy']);

        Route::apiResource('roles', RoleController::class)
            ->middlewareFor(['index', 'show'], 'permission:admin.roles.view')
            ->middlewareFor(['store', 'update', 'destroy'], 'permission:admin.roles.manage');
    });
});
