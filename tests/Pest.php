<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Referer simula una request del SPA propio: Sanctum's EnsureFrontendRequestsAreStateful
// solo activa sesión/cookies para requests cuyo Origin/Referer esté en sanctum.stateful.
uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(fn () => $this->withHeader('Referer', config('app.url')))
    ->in('Feature');
