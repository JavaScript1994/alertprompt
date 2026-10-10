<?php

namespace Tests;

use App\Enums\SensitiveAction;
use App\Models\SensitiveActionConfirmation;
use App\Services\Mfa\MfaService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Los tests de módulos con acciones sensibles (exportar, roles,
     * proveedores) lo activan en su beforeEach: actingAs() deja además la
     * re-autenticación hecha. La exigencia en sí se prueba en tests/Feature/Mfa.
     */
    protected bool $reauthConfirmed = false;

    /**
     * actingAs() deja la sesión con el segundo factor ya verificado, como
     * después de un login completo, para que los tests de cada módulo no
     * dependan del flujo de MFA. Los tests de MFA usan actingAsWithoutMfa().
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        parent::actingAs($user, $guard);

        if ($this->reauthConfirmed) {
            foreach (SensitiveAction::cases() as $action) {
                SensitiveActionConfirmation::query()->create([
                    'user_id' => $user->getAuthIdentifier(),
                    'action' => $action,
                    'confirmed_at' => now(),
                    'expires_at' => now()->addMinutes(15),
                    'ip' => '127.0.0.1',
                ]);
            }
        }

        return $this->withSession([MfaService::SESSION_KEY => ['user_id' => $user->getAuthIdentifier(), 'at' => time()]]);
    }

    /** Sesión autenticada SIN segundo factor (recién pasó la contraseña). */
    public function actingAsWithoutMfa(Authenticatable $user, $guard = null): static
    {
        parent::actingAs($user, $guard);

        return $this;
    }
}
