<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CampaignStatus;
use App\Enums\TemplateStatus;
use App\Enums\TenantStatus;
use App\Enums\TenantType;
use App\Models\Campaign;
use App\Models\Consent;
use App\Models\Contact;
use App\Models\Plan;
use App\Models\Template;
use App\Models\Tenant;
use App\Services\CampaignAudienceService;
use App\Services\Clients\ClientManager;
use App\Services\Memberships\MembershipManager;
use App\Services\Modules\TenantModules;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Empresa de prueba para recorrer el panel de administración: clientes,
 * supervisión, modo soporte, módulos, membresía y facturación. No envía
 * correos. Idempotente por RUC.
 *
 * El RUC es ficticio (pasa el dígito verificador, no corresponde a nadie).
 */
class DemoClientSeeder extends Seeder
{
    public const RUC = '20600000005';

    private const CONTACTS = [
        ['Rosa Quispe Mamani', '+51900100001', 'Miraflores', true],
        ['Jorge Huamán Torres', '+51900100002', 'San Isidro', true],
        ['Lucía Flores Ramos', '+51900100003', 'Surco', true],
        ['Carlos Mendoza Ríos', '+51900100004', 'La Molina', false],
        ['Ana Chávez Paredes', '+51900100005', 'Barranco', true],
        ['Miguel Rojas Salazar', '+51900100006', 'Lince', false],
        ['Patricia Vargas León', '+51900100007', 'Jesús María', true],
        ['Luis Castillo Díaz', '+51900100008', 'San Borja', true],
        ['Carmen Gutiérrez Soto', '+51900100009', 'Pueblo Libre', false],
        ['Diego Morales Cruz', '+51900100010', 'Magdalena', true],
        ['Sofía Ramírez Vega', '+51900100011', 'Chorrillos', true],
        ['Andrés Silva Campos', '+51900100012', 'San Miguel', true],
    ];

    public function run(): void
    {
        if (Tenant::query()->where('document_number', self::RUC)->exists()) {
            $this->command?->info('La empresa de prueba ya existe.');

            return;
        }

        $tenant = Tenant::query()->create([
            'name' => 'Empresa de Prueba SAC',
            'type' => TenantType::Company,
            'document_type' => 'ruc',
            'document_number' => self::RUC,
            'contact_email' => 'contacto@empresaprueba.pe',
            'contact_phone' => '+51 1 4000000',
            'address' => 'Av. Javier Prado Este 1000, San Isidro, Lima',
            'plan' => 'starter',
            'status' => TenantStatus::Active,
            'settings' => ['timezone' => 'America/Lima'],
        ]);

        // Usuario con contraseña conocida y sin invitación por correo.
        $admin = app(ClientManager::class)->createUser($tenant, 'Admin Empresa de Prueba', 'admin@empresaprueba.pe', 'client-admin');
        $admin->forceFill(['password' => Hash::make('password'), 'email_verified_at' => now()])->save();

        app(TenantModules::class)->sync($tenant, app(TenantModules::class)->defaultsFor('growth'));

        TenantContext::set($tenant->id);

        $contacts = [];
        foreach (self::CONTACTS as [$name, $phone, $district, $consented]) {
            $contact = Contact::query()->create([
                'name' => $name,
                'phone' => $phone,
                'email' => null,
                'attributes' => ['nombre' => explode(' ', $name)[0], 'distrito' => $district],
            ]);

            if ($consented) {
                foreach (['whatsapp', 'sms'] as $channel) {
                    Consent::query()->create([
                        'contact_id' => $contact->id,
                        'channel' => $channel,
                        'granted_at' => now()->subMonths(2),
                        'source' => 'formulario_web',
                        'ip' => '190.0.0.1',
                        'evidence_text' => 'Acepto recibir promociones de Empresa de Prueba SAC por WhatsApp y SMS. Puedo darme de baja cuando quiera.',
                    ]);
                }
            }

            $contacts[] = $contact->id;
        }

        $promo = Template::query()->create([
            'channel' => 'whatsapp',
            'category' => 'marketing',
            'name' => 'Promo de temporada',
            'body' => 'Hola {{nombre}}, esta semana tienes 20% de descuento en tu tienda de {{distrito}}. Responde BAJA para no recibir más mensajes.',
            'variables' => ['nombre', 'distrito'],
            'status' => TemplateStatus::Approved,
        ]);

        Template::query()->create([
            'channel' => 'sms',
            'category' => 'utility',
            'name' => 'Recordatorio de cita',
            'body' => 'Hola {{nombre}}, te recordamos tu cita de mañana. Para reprogramar responde a este mensaje.',
            'variables' => ['nombre'],
            'status' => TemplateStatus::Approved,
        ]);

        $campaign = Campaign::query()->create([
            'template_id' => $promo->id,
            'channel' => $promo->channel,
            'name' => 'Promo de temporada (borrador)',
            'status' => CampaignStatus::Draft,
        ]);
        app(CampaignAudienceService::class)->enroll($campaign, $contacts);

        // Membresía vigente con las condiciones del catálogo (plan Growth):
        // activa el plan y emite su primera proforma.
        $growth = Plan::query()->where('key', 'growth')->firstOrFail();
        app(MembershipManager::class)->create($tenant, [
            'plan' => 'growth',
            'billing_cycle' => 'monthly',
            'price' => (string) $growth->monthly_price,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addYear()->subDay()->toDateString(),
            'quotas' => $growth->quotas,
            'contract_reference' => 'CTR-PRUEBA-001',
        ]);

        $this->command?->info('Empresa de prueba creada: admin@empresaprueba.pe / password');
    }
}
