<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contact> */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake('es_PE')->name(),
            'phone' => '+519'.fake()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'attributes' => [],
        ];
    }
}
