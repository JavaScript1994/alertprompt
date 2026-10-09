<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Referer simula una request del SPA propio: Sanctum's EnsureFrontendRequestsAreStateful
// solo activa sesión/cookies para requests cuyo Origin/Referer esté en sanctum.stateful.
uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(fn () => $this->withHeader('Referer', config('app.url')))
    ->in('Feature');

/** Dueño de la plataforma (crea el tenant AlertPrompt si aún no existe). */
function platformOwner(): User
{
    $platform = Tenant::platform() ?? Tenant::factory()->platform()->create();

    return User::factory()->for($platform)->create();
}
