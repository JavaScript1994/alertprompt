<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Channel;
use App\Models\Consent;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Consent> */
class ConsentFactory extends Factory
{
    protected $model = Consent::class;

    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'channel' => Channel::WhatsApp,
            'granted_at' => now()->subMonth(),
            'revoked_at' => null,
            'source' => 'web_form',
            'ip' => fake()->ipv4(),
            'evidence_text' => 'Acepto recibir mensajes comerciales. Ley N° 32323.',
        ];
    }
}
