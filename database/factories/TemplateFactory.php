<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Channel;
use App\Enums\TemplateCategory;
use App\Enums\TemplateStatus;
use App\Models\Template;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Template> */
class TemplateFactory extends Factory
{
    protected $model = Template::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'channel' => Channel::WhatsApp,
            'category' => TemplateCategory::Marketing,
            'name' => fake()->unique()->words(3, true),
            'body' => 'Hola {{nombre}}, tu pedido {{pedido}} está listo.',
            'variables' => ['nombre', 'pedido'],
            'status' => TemplateStatus::Draft,
        ];
    }
}
