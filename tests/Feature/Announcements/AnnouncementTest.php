<?php

declare(strict_types=1);

use App\Models\Announcement;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AnnouncementMail;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

beforeEach(function () {
    Notification::fake();
    $this->owner = platformOwner();
    $this->basico = Tenant::factory()->create(['name' => 'Básico SAC', 'plan' => 'basico']);
    $this->avanzado = Tenant::factory()->create(['name' => 'Avanzado SAC', 'plan' => 'avanzado']);
    $this->adminBasico = User::factory()->for($this->basico)->create();
    $this->userBasico = User::factory()->for($this->basico)->withRole('client-user')->create();
    $this->adminAvanzado = User::factory()->for($this->avanzado)->create();
});

/** Publica un comunicado como la plataforma y devuelve su id. */
function publish(TestCase $test, User $owner, array $overrides = []): int
{
    return (int) $test->actingAs($owner)->postJson('/api/admin/announcements', [
        'type' => 'feature',
        'title' => 'Nuevo: reportes por categoría',
        'body' => 'Ahora puedes ver cuánto gastas por categoría de plantilla.',
        'audience' => 'all',
        'published_at' => now()->subMinute()->toIso8601String(),
        ...$overrides,
    ])->assertCreated()->json('data.id');
}

function titlesFor(TestCase $test, User $user): array
{
    return collect($test->actingAs($user)->getJson('/api/announcements')->assertOk()->json('data'))->pluck('title')->all();
}

it('shows an announcement for everyone to every client user, not to the platform', function () {
    publish($this, $this->owner);

    expect(titlesFor($this, $this->userBasico))->toBe(['Nuevo: reportes por categoría'])
        ->and(titlesFor($this, $this->adminAvanzado))->toHaveCount(1)
        ->and(titlesFor($this, $this->owner))->toBe([]);
});

it('targets clients by plan', function () {
    publish($this, $this->owner, ['title' => 'Para Avanzado', 'audience' => 'plans', 'plan_keys' => ['avanzado']]);

    expect(titlesFor($this, $this->adminAvanzado))->toBe(['Para Avanzado'])
        ->and(titlesFor($this, $this->adminBasico))->toBe([]);
});

it('targets specific clients', function () {
    publish($this, $this->owner, ['title' => 'Solo Básico', 'audience' => 'tenants', 'tenant_ids' => [$this->basico->id]]);

    expect(titlesFor($this, $this->userBasico))->toBe(['Solo Básico'])
        ->and(titlesFor($this, $this->adminAvanzado))->toBe([]);
});

it('targets only administrators when asked', function () {
    publish($this, $this->owner, ['type' => 'action_required', 'title' => 'Actualiza tu método de pago', 'admins_only' => true]);

    expect(titlesFor($this, $this->adminBasico))->toBe(['Actualiza tu método de pago'])
        ->and(titlesFor($this, $this->userBasico))->toBe([]);
});

it('hides drafts, scheduled and expired announcements', function () {
    publish($this, $this->owner, ['title' => 'Borrador', 'published_at' => null]);
    publish($this, $this->owner, ['title' => 'Programado', 'published_at' => now()->addDay()->toIso8601String()]);
    publish($this, $this->owner, ['title' => 'Vencido', 'published_at' => now()->subDays(3)->toIso8601String(), 'expires_at' => now()->subDay()->toIso8601String()]);

    expect(titlesFor($this, $this->userBasico))->toBe([]);

    $this->travel(2)->days();
    expect(titlesFor($this, $this->userBasico))->toBe(['Programado']);
});

it('tracks reads, dismissals and read-all per user', function () {
    $first = publish($this, $this->owner, ['title' => 'Uno']);
    $second = publish($this, $this->owner, ['type' => 'action_required', 'title' => 'Dos']);

    $this->actingAs($this->userBasico)->postJson("/api/announcements/{$first}/read")->assertNoContent();
    $this->postJson("/api/announcements/{$second}/dismiss")->assertNoContent();

    $items = collect($this->getJson('/api/announcements')->json('data'))->keyBy('title');
    expect($items['Uno']['read_at'])->not->toBeNull()
        ->and($items['Uno']['dismissed_at'])->toBeNull()
        ->and($items['Dos']['dismissed_at'])->not->toBeNull();

    // Otro usuario no hereda las lecturas.
    expect(collect($this->actingAs($this->adminBasico)->getJson('/api/announcements')->json('data'))->whereNotNull('read_at'))->toHaveCount(0);
    $this->postJson('/api/announcements/read-all')->assertNoContent();
    expect(collect($this->getJson('/api/announcements')->json('data'))->whereNull('read_at'))->toHaveCount(0);
});

it('does not let a user mark an announcement that is not for them', function () {
    $id = publish($this, $this->owner, ['audience' => 'tenants', 'tenant_ids' => [$this->avanzado->id]]);

    $this->actingAs($this->userBasico)->postJson("/api/announcements/{$id}/read")->assertNotFound();
});

it('never emails promotions, even if asked', function () {
    $id = publish($this, $this->owner, ['type' => 'promotion', 'title' => 'Prueba la IA', 'send_email' => true]);

    expect(Announcement::query()->findOrFail($id)->send_email)->toBeFalse();
    Artisan::call('announcements:send-emails');
    Notification::assertNothingSent();
});

it('emails incidents once, only to their recipients and only once published', function () {
    publish($this, $this->owner, [
        'type' => 'incident', 'title' => 'Demoras en WhatsApp', 'send_email' => true,
        'audience' => 'tenants', 'tenant_ids' => [$this->basico->id],
        'published_at' => now()->addHour()->toIso8601String(),
    ]);

    Artisan::call('announcements:send-emails');
    Notification::assertNothingSent();

    $this->travel(61)->minutes();
    Artisan::call('announcements:send-emails');
    Artisan::call('announcements:send-emails');

    Notification::assertSentTo($this->userBasico, AnnouncementMail::class);
    Notification::assertSentTo($this->adminBasico, AnnouncementMail::class);
    Notification::assertNotSentTo($this->adminAvanzado, AnnouncementMail::class);
    Notification::assertSentTimes(AnnouncementMail::class, 2);
});

it('resolves incidents only', function () {
    $incident = publish($this, $this->owner, ['type' => 'incident', 'title' => 'Caída']);
    $notice = publish($this, $this->owner, ['type' => 'notice', 'title' => 'Aviso']);

    $this->actingAs($this->owner)->postJson("/api/admin/announcements/{$incident}/resolve")->assertOk()->assertJsonPath('data.status', 'resolved');
    $this->postJson("/api/admin/announcements/{$notice}/resolve")->assertUnprocessable();

    $seen = collect(titlesFor($this, $this->userBasico));
    expect($seen)->toContain('Caída');
    expect(collect($this->getJson('/api/announcements')->json('data'))->firstWhere('title', 'Caída')['resolved_at'])->not->toBeNull();
});

it('reports reads per client', function () {
    $id = publish($this, $this->owner);
    $this->actingAs($this->userBasico)->postJson("/api/announcements/{$id}/read")->assertNoContent();

    $stats = collect($this->actingAs($this->owner)->getJson("/api/admin/announcements/{$id}/stats")->assertOk()->json('data'))->keyBy('tenant_name');

    expect($stats['Básico SAC'])->toMatchArray(['recipients' => 2, 'reads' => 1])
        ->and($stats['Avanzado SAC'])->toMatchArray(['recipients' => 1, 'reads' => 0]);
});

it('only accepts internal or https links', function (string $link) {
    $this->actingAs($this->owner)->postJson('/api/admin/announcements', [
        'type' => 'notice', 'title' => 'X', 'body' => 'Y', 'audience' => 'all',
        'link_url' => $link, 'link_label' => 'Ver',
    ])->assertUnprocessable()->assertJsonValidationErrors('link_url');
})->with(['javascript:alert(1)', 'http://inseguro.pe', 'ftp://x.pe']);

it('requires a target when the audience is plans or clients', function () {
    $this->actingAs($this->owner)->postJson('/api/admin/announcements', [
        'type' => 'notice', 'title' => 'X', 'body' => 'Y', 'audience' => 'tenants',
    ])->assertUnprocessable()->assertJsonValidationErrors('tenant_ids');
});

it('keeps the management of announcements in the platform', function () {
    $this->actingAs($this->adminBasico)->getJson('/api/admin/announcements')->assertForbidden();
    $this->actingAs($this->adminBasico)->postJson('/api/admin/announcements', [])->assertForbidden();
});
