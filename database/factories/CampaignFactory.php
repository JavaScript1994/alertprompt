<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Models\Campaign;
use App\Models\Template;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Campaign> */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'template_id' => Template::factory(),
            'channel' => Channel::WhatsApp,
            'name' => fake()->unique()->words(3, true),
            'status' => CampaignStatus::Running,
            'stats' => [],
        ];
    }
}
