<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SetPasswordLink;
use App\Rules\ReadableBrandColor;
use App\Services\Branding\BrandingService;
use App\Support\TenantSlug;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

function enableBranding(Tenant $tenant): Tenant
{
    DB::table('tenant_modules')->insertOrIgnore(['tenant_id' => $tenant->id, 'module' => 'branding', 'created_at' => now(), 'updated_at' => now()]);

    return $tenant;
}

/** URL absoluta en el subdominio de un cliente, con el esquema y puerto de APP_URL. */
function sub(string $slug, string $path): string
{
    $app = parse_url((string) config('app.url'));
    $port = isset($app['port']) ? ':'.$app['port'] : '';

    return "{$app['scheme']}://{$slug}.localhost{$port}{$path}";
}

/** Peticiones desde el SPA servido en ese subdominio (Referer para Sanctum). */
function onSubdomain(TestCase $test, string $slug): TestCase
{
    return $test->withHeader('Referer', sub($slug, '/'));
}

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->andina = Tenant::factory()->create(['name' => 'Andina SAC', 'slug' => 'andina']);
    // El factory activa todo el catálogo; aquí la marca empieza apagada.
    DB::table('tenant_modules')->where('tenant_id', $this->andina->id)->where('module', 'branding')->delete();
});

it('generates a unique, non reserved slug from the name', function () {
    Tenant::factory()->create(['slug' => 'pyme-sac']);

    expect(TenantSlug::unique('Pyme SAC'))->toBe('pyme-sac-2')
        ->and(TenantSlug::unique('Ñandú & Cía. S.A.C.'))->toBe('nandu-cia-sac')
        ->and(TenantSlug::unique('Admin'))->toBe('empresa-admin')
        ->and(TenantSlug::unique('X'))->toStartWith('empresa-');
});

it('gives every new client a slug', function () {
    $this->actingAs(platformOwner())->postJson('/api/admin/clients', [
        'type' => 'company', 'name' => 'Lima Retail SAC', 'document_type' => 'ruc', 'document_number' => '20100047218',
        'admin_first_name' => 'Ana', 'admin_last_name' => 'Ríos', 'admin_email' => 'ana@limaretail.pe',
    ])->assertCreated()->assertJsonPath('data.slug', 'lima-retail-sac')
        ->assertJsonPath('data.login_url', sub('lima-retail-sac', ''));
});

it('serves the generic login on the base domain', function () {
    $this->getJson('/api/branding')->assertOk()
        ->assertJsonPath('data.tenant', null)
        ->assertJsonPath('data.unknown', false)
        ->assertJsonPath('data.branded', false);
});

it('shows only the company name on a subdomain without the branding module', function () {
    $this->andina->forceFill(['brand_color' => '#123456', 'brand_title' => 'Hola'])->save();

    onSubdomain($this, 'andina')->getJson(sub('andina', '/api/branding'))->assertOk()
        ->assertJsonPath('data.tenant.name', 'Andina SAC')
        ->assertJsonPath('data.branded', false)
        ->assertJsonPath('data.color', null)
        ->assertJsonPath('data.title', null);
});

it('shows logo, color and title with the branding module', function () {
    enableBranding($this->andina);
    $this->andina->forceFill(['brand_color' => '#0b3d91', 'brand_title' => 'Bienvenido a Andina'])->save();
    app(BrandingService::class)->setLogo($this->andina, fakePhoto('logo.png'));

    $branding = onSubdomain($this, 'andina')->getJson(sub('andina', '/api/branding'))->assertOk()
        ->assertJsonPath('data.branded', true)
        ->assertJsonPath('data.color', '#0b3d91')
        ->assertJsonPath('data.title', 'Bienvenido a Andina');

    // El logo es público (se ve antes de iniciar sesión).
    onSubdomain($this, 'andina')->get(sub('andina', $branding->json('data.logo_url')))->assertOk()->assertHeader('Content-Type', 'image/png');
});

it('does not serve the logo without the module or on the base domain', function () {
    app(BrandingService::class)->setLogo($this->andina, fakePhoto('logo.png'));

    onSubdomain($this, 'andina')->get(sub('andina', '/api/branding/logo'))->assertNotFound();
    $this->get('/api/branding/logo')->assertNotFound();
});

it('flags an unknown subdomain and refuses to log in there', function () {
    onSubdomain($this, 'no-existe')->getJson(sub('no-existe', '/api/branding'))->assertOk()->assertJsonPath('data.unknown', true);

    $user = User::factory()->for($this->andina)->create();
    onSubdomain($this, 'no-existe')->postJson(sub('no-existe', '/api/login'), ['email' => $user->email, 'password' => 'password'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('only lets users of the company log in on its subdomain', function () {
    $own = User::factory()->for($this->andina)->create();
    $other = User::factory()->for(Tenant::factory())->create();
    $owner = platformOwner();

    onSubdomain($this, 'andina')->postJson(sub('andina', '/api/login'), ['email' => $own->email, 'password' => 'password'])
        ->assertOk()->assertJsonPath('mfa_required', true);

    foreach ([$other, $owner] as $user) {
        onSubdomain($this, 'andina')->postJson(sub('andina', '/api/login'), ['email' => $user->email, 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Este acceso es solo para usuarios de Andina SAC. Entra desde el enlace de tu empresa.');
    }
});

it('does not reveal the company restriction before a valid password', function () {
    $other = User::factory()->for(Tenant::factory())->create();

    onSubdomain($this, 'andina')->postJson(sub('andina', '/api/login'), ['email' => $other->email, 'password' => 'otra'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'El correo o la contraseña son incorrectos.');
});

it('rejects a session of another company on a subdomain', function () {
    $other = User::factory()->for(Tenant::factory())->create();

    onSubdomain($this, 'andina')->actingAs($other)->getJson(sub('andina', '/api/contacts'))
        ->assertForbidden()->assertJsonPath('code', 'wrong_domain');
});

it('lets the company work normally on its own subdomain', function () {
    $own = User::factory()->for($this->andina)->create();

    onSubdomain($this, 'andina')->actingAs($own)->getJson(sub('andina', '/api/contacts'))->assertOk();
});

it('lets the client administrator set color and title with the module', function () {
    enableBranding($this->andina);
    $admin = User::factory()->for($this->andina)->create();

    $this->actingAs($admin)->putJson('/api/settings/branding', ['brand_color' => '#0B3D91', 'brand_title' => 'Bienvenido'])
        ->assertOk()
        ->assertJsonPath('data.color', '#0b3d91')
        ->assertJsonPath('data.login_url', sub('andina', ''));
});

it('rejects colors where white text would not be readable', function (string $color) {
    enableBranding($this->andina);
    $admin = User::factory()->for($this->andina)->create();

    $this->actingAs($admin)->putJson('/api/settings/branding', ['brand_color' => $color])
        ->assertUnprocessable()->assertJsonValidationErrors('brand_color');
})->with(['#ffff00', '#9be7de', 'rojo', '#12345']);

it('accepts PNG/JPG/WebP logos and rejects SVG', function () {
    enableBranding($this->andina);
    $admin = User::factory()->for($this->andina)->create();

    $this->actingAs($admin)->post('/api/settings/branding/logo', ['logo' => fakePhoto('logo.png')], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('data.logo_url', fn (string $url) => str_starts_with($url, '/api/settings/branding/logo?v='));

    $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    $this->post('/api/settings/branding/logo', ['logo' => $svg], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('logo');

    $this->deleteJson('/api/settings/branding/logo')->assertOk()->assertJsonPath('data.logo_url', null);
});

it('requires the branding module and settings.manage', function () {
    $admin = User::factory()->for($this->andina)->create();
    $this->actingAs($admin)->getJson('/api/settings/branding')->assertForbidden();

    enableBranding($this->andina);
    $user = User::factory()->for($this->andina)->withRole('client-user')->create();
    $this->actingAs($user)->putJson('/api/settings/branding', ['brand_title' => 'X'])->assertForbidden();
});

it('lets only the platform change a slug, with validation', function () {
    $owner = platformOwner();
    $other = Tenant::factory()->create(['slug' => 'ocupado']);
    $payload = fn (string $slug) => [
        'name' => 'Andina SAC', 'document_type' => 'ruc', 'document_number' => '20100047218', 'slug' => $slug,
    ];

    $this->actingAs($owner)->putJson("/api/admin/clients/{$this->andina->id}", $payload('Andina-Peru'))
        ->assertOk()->assertJsonPath('data.slug', 'andina-peru');

    foreach (['ocupado', 'www', '-mal', 'a'] as $invalid) {
        $this->putJson("/api/admin/clients/{$this->andina->id}", $payload($invalid))->assertUnprocessable()->assertJsonValidationErrors('slug');
    }

    expect($other->fresh()->slug)->toBe('ocupado');
});

it('sends invitations to the branded subdomain only with the module', function () {
    $admin = User::factory()->for($this->andina)->create();
    $roleId = Role::query()->whereNull('tenant_id')->where('name', 'client-user')->value('id');

    // La URL se arma al enviar (renderizar) el correo: se revisa en cada momento.
    $inviteUrl = function (string $email) use ($roleId): string {
        $this->postJson('/api/users', ['first_name' => 'Ana', 'last_name' => 'Ríos', 'email' => $email, 'role_id' => $roleId])->assertCreated();
        $to = User::query()->withoutGlobalScopes()->where('email', $email)->sole();
        $url = '';
        Notification::assertSentTo($to, SetPasswordLink::class, function (SetPasswordLink $n) use ($to, &$url) {
            $url = (string) $n->toMail($to)->actionUrl;

            return true;
        });

        return $url;
    };

    $this->actingAs($admin);
    expect($inviteUrl('ana@andina.pe'))->toStartWith(rtrim((string) config('app.url'), '/').'/reset-password');

    enableBranding($this->andina);
    expect($inviteUrl('beto@andina.pe'))->toStartWith(sub('andina', '/reset-password'));
});

it('measures contrast against white (WCAG AA)', function () {
    expect(ReadableBrandColor::contrastWithWhite('#147b70'))->toBeGreaterThan(4.5)
        ->and(ReadableBrandColor::contrastWithWhite('#ffff00'))->toBeLessThan(1.5);
});
