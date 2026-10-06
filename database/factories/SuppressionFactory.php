<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Channel;
use App\Enums\SuppressionReason;
use App\Models\Suppression;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Suppression> */
class SuppressionFactory extends Factory
{
    protected $model = Suppression::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'channel' => Channel::WhatsApp,
            'identifier' => '+519'.fake()->numerify('########'),
            'reason' => SuppressionReason::Unsubscribed,
        ];
    }
}
