<?php

declare(strict_types=1);

namespace App\Services;

class CampaignPacer
{
    public function __construct(private readonly float $failureThreshold) {}

    /**
     * §6.1: si (failed + blocked + reported) / sent > umbral, pausar.
     * Sin envíos todavía no hay señal — no pausa por falta de datos.
     */
    public function shouldPause(int $sent, int $failed, int $blocked = 0, int $reported = 0): bool
    {
        if ($sent <= 0) {
            return false;
        }

        $badOutcomes = $failed + $blocked + $reported;

        return ($badOutcomes / $sent) > $this->failureThreshold;
    }
}
