<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\BillingCycle;
use App\Models\Membership;
use Carbon\CarbonImmutable;

/** Cálculos de períodos de facturación de una membresía. */
final class BillingPeriod
{
    /** Inicio del ciclo de facturación que contiene $today (o null si fuera de vigencia). */
    public static function currentStart(Membership $membership, CarbonImmutable $today): ?CarbonImmutable
    {
        $start = CarbonImmutable::parse($membership->starts_at)->startOfDay();
        $end = CarbonImmutable::parse($membership->ends_at)->startOfDay();

        if ($today->lessThan($start) || $today->greaterThan($end)) {
            return null;
        }

        $months = $membership->billing_cycle === BillingCycle::Monthly ? 1 : 12;
        $elapsed = (int) floor($start->diffInMonths($today) / $months) * $months;

        return $start->addMonthsNoOverflow($elapsed);
    }

    /**
     * Meses completos de un período anual que empiezan después de $date: los
     * que se prorratean al contratar y los que se devuelven al cancelar. El
     * mes en curso nunca cuenta (igual que en las membresías mensuales).
     */
    public static function monthsLeftInYear(CarbonImmutable $periodStart, CarbonImmutable $date): int
    {
        return collect(range(1, 11))->filter(fn (int $k) => $periodStart->addMonthsNoOverflow($k)->greaterThan($date))->count();
    }
}
