<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Channel;
use App\Models\Consent;
use App\Models\Contact;
use App\Models\Tenant;
use Faker\Factory as Faker;
use Faker\Generator;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    // Nombres masculinos peruanos comunes
    private const NOMBRES_M = [
        'Carlos', 'Juan', 'Luis', 'Jorge', 'Miguel', 'José', 'Manuel', 'Pedro',
        'Roberto', 'Ricardo', 'Eduardo', 'Fernando', 'Alejandro', 'Diego', 'Andrés',
        'Daniel', 'Pablo', 'Sergio', 'Raúl', 'Enrique', 'Alfredo', 'Marco', 'Oscar',
        'César', 'Víctor', 'Gonzalo', 'Ramiro', 'Augusto', 'Renzo', 'Aldo',
    ];

    // Nombres femeninos peruanos comunes
    private const NOMBRES_F = [
        'María', 'Rosa', 'Ana', 'Carmen', 'Patricia', 'Sandra', 'Mónica', 'Silvia',
        'Gloria', 'Elena', 'Lucia', 'Sofía', 'Valeria', 'Claudia', 'Andrea',
        'Gabriela', 'Paola', 'Natalia', 'Verónica', 'Alejandra', 'Karina', 'Carla',
        'Giuliana', 'Fiorella', 'Mariela', 'Roxana', 'Janet', 'Milagros', 'Yessenia', 'Lupe',
    ];

    // Apellidos peruanos comunes
    private const APELLIDOS = [
        'García', 'Rodríguez', 'López', 'Flores', 'Quispe', 'Mamani', 'Torres',
        'Sánchez', 'Romero', 'Pérez', 'Vargas', 'Cruz', 'Huanca', 'Ccopa',
        'Condori', 'Gonzales', 'Chávez', 'Mendoza', 'Díaz', 'Ramos', 'Villanueva',
        'Paredes', 'Gutierrez', 'Rojas', 'Herrera', 'Medina', 'Castillo', 'Morales',
        'Ortega', 'Reyes', 'Vega', 'Delgado', 'Cabrera', 'Poma', 'Huamán',
        'Ccahua', 'Apaza', 'Zapata', 'Quispicocha', 'Yucra',
    ];

    // Operadoras peruanas reales (prefijos)
    private const PREFIJOS_PE = ['9', '9', '9', '9']; // todos los móviles peruanos empiezan con 9

    private const DISTRITOS_LIMA = [
        'Miraflores', 'San Isidro', 'Surco', 'La Molina', 'San Borja',
        'Barranco', 'Lince', 'Jesús María', 'Pueblo Libre', 'Magdalena',
        'San Miguel', 'Breña', 'Rímac', 'Los Olivos', 'Independencia',
        'Comas', 'SJL', 'Villa El Salvador', 'Villa María del Triunfo', 'Chorrillos',
    ];

    public function run(): void
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->command->warn('No hay tenant. Ejecuta TenantSeeder primero.');

            return;
        }

        app()->instance('current_tenant_id', $tenant->id);

        $faker = Faker::create('es_PE');
        $usedPhones = [];
        $usedEmails = [];
        $count = 0;
        $target = 200;

        while ($count < $target) {
            $esMasculino = $faker->boolean(50);
            $nombre = $esMasculino
                ? $faker->randomElement(self::NOMBRES_M)
                : $faker->randomElement(self::NOMBRES_F);
            $apellido1 = $faker->randomElement(self::APELLIDOS);
            $apellido2 = $faker->randomElement(self::APELLIDOS);
            $nombreCompleto = "{$nombre} {$apellido1} {$apellido2}";

            $phone = $this->generarCelularPeru($faker);
            if (in_array($phone, $usedPhones, true)) {
                continue;
            }
            $usedPhones[] = $phone;

            $slug = strtolower(
                iconv('UTF-8', 'ASCII//TRANSLIT', "{$nombre}.{$apellido1}").$faker->numberBetween(1, 999)
            );
            $dominio = $faker->randomElement(['gmail.com', 'hotmail.com', 'yahoo.com', 'outlook.com']);
            $email = "{$slug}@{$dominio}";
            if (in_array($email, $usedEmails, true)) {
                continue;
            }
            $usedEmails[] = $email;

            $distrito = $faker->randomElement(self::DISTRITOS_LIMA);

            $contact = Contact::create([
                'tenant_id' => $tenant->id,
                'phone' => $phone,
                'email' => $email,
                'name' => $nombreCompleto,
                'attributes' => [
                    'distrito' => $distrito,
                    'dni' => $faker->numerify('########'),
                    'fecha_nacimiento' => $faker->dateTimeBetween('-65 years', '-18 years')->format('Y-m-d'),
                    'genero' => $esMasculino ? 'M' : 'F',
                ],
            ]);

            // ~70% de contactos tienen consentimiento WhatsApp
            if ($faker->boolean(70)) {
                Consent::create([
                    'contact_id' => $contact->id,
                    'channel' => Channel::WhatsApp->value,
                    'granted_at' => $faker->dateTimeBetween('-1 year', 'now'),
                    'revoked_at' => null,
                    'source' => $faker->randomElement(['web_form', 'qr_code', 'chatbot', 'landing_page']),
                    'ip' => $faker->localIpv4(),
                    'evidence_text' => 'Acepto recibir mensajes de WhatsApp con información comercial y promociones de '.$tenant->name.'. Ley N° 32323.',
                ]);
            }

            // ~50% tienen consentimiento SMS
            if ($faker->boolean(50)) {
                Consent::create([
                    'contact_id' => $contact->id,
                    'channel' => Channel::Sms->value,
                    'granted_at' => $faker->dateTimeBetween('-1 year', 'now'),
                    'revoked_at' => null,
                    'source' => $faker->randomElement(['web_form', 'landing_page']),
                    'ip' => $faker->localIpv4(),
                    'evidence_text' => 'Acepto recibir SMS con información y ofertas de '.$tenant->name.'. Ley N° 32323.',
                ]);
            }

            // ~60% tienen consentimiento email
            if ($faker->boolean(60)) {
                Consent::create([
                    'contact_id' => $contact->id,
                    'channel' => Channel::Email->value,
                    'granted_at' => $faker->dateTimeBetween('-1 year', 'now'),
                    'revoked_at' => null,
                    'source' => $faker->randomElement(['web_form', 'newsletter_signup']),
                    'ip' => $faker->localIpv4(),
                    'evidence_text' => 'Acepto recibir correos electrónicos con novedades y promociones de '.$tenant->name.'. Ley N° 32323.',
                ]);
            }

            $count++;
        }

        $this->command->info("Creados {$count} contactos peruanos con consentimientos para tenant '{$tenant->name}'.");
    }

    private function generarCelularPeru(Generator $faker): string
    {
        // Móviles peruanos: +51 9XXXXXXXX (9 dígitos totales tras el código de país)
        // Segundo dígito según operadora: Movistar 9[1-5], Claro 9[6-9], Entel/Bitel 9[0,7-8]
        $segundoDigito = (string) $faker->randomElement([1, 2, 3, 4, 5, 6, 7, 8, 9]);
        $resto = $faker->numerify('#######'); // 7 dígitos

        return '+519'.$segundoDigito.$resto;
    }
}
