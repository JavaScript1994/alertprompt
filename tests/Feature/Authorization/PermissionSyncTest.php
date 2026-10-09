<?php

declare(strict_types=1);

use App\Enums\RoleScope;
use App\Models\Role;
use App\Services\Authorization\PermissionRegistry;
use Spatie\Permission\Models\Permission;

it('mirrors every permission of the config tree into the permissions table', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::query()->pluck('name')->sort()->values()->all())
        ->toEqual(collect(app(PermissionRegistry::class)->all())->sort()->values()->all());
});

it('is idempotent', function () {
    $this->artisan('permissions:sync')->assertSuccessful();
    $permissions = Permission::query()->count();
    $roles = Role::query()->count();

    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::query()->count())->toBe($permissions)
        ->and(Role::query()->count())->toBe($roles);
});

it('prunes permissions that no longer exist in the config', function () {
    Permission::query()->create(['name' => 'legacy.removed', 'guard_name' => 'web']);

    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::query()->where('name', 'legacy.removed')->exists())->toBeFalse();
});

it('always gives the platform owner every permission, including new ones', function () {
    $owner = Role::query()->where('name', 'platform-owner')->firstOrFail();
    $owner->revokePermissionTo('admin.roles.manage');

    $this->artisan('permissions:sync')->assertSuccessful();

    expect($owner->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toEqual(collect(app(PermissionRegistry::class)->all())->sort()->values()->all());
});

it('keeps the owner edits on other system roles', function () {
    $viewer = Role::query()->where('name', 'client-viewer')->firstOrFail();
    $viewer->givePermissionTo('contacts.create');

    $this->artisan('permissions:sync')->assertSuccessful();

    expect($viewer->fresh()->hasPermissionTo('contacts.create'))->toBeTrue();
});

it('gives client roles only client permissions', function () {
    $registry = app(PermissionRegistry::class);
    $adminPermissions = Role::query()->where('name', 'client-admin')->firstOrFail()->permissions->pluck('name');

    expect($adminPermissions->filter(fn (string $name) => str_starts_with($name, 'admin.')))->toBeEmpty()
        ->and($adminPermissions->count())->toBe(count($registry->forScope(RoleScope::Client)));
});

it('rejects a system role that references an unknown permission', function () {
    app(PermissionRegistry::class)->resolve(['contacts.view', 'contacts.fly']);
})->throws(InvalidArgumentException::class, 'contacts.fly');

it('adds brand-new permissions to the system roles whose definition includes them', function () {
    $admin = Role::query()->where('name', 'client-admin')->firstOrFail();
    $admin->revokePermissionTo('contacts.delete'); // edición del dueño: se respeta
    Permission::query()->where('name', 'reports.export')->delete(); // simula permiso aún no sincronizado

    $this->artisan('permissions:sync')->assertSuccessful();

    $admin = $admin->fresh();
    expect($admin->hasPermissionTo('reports.export'))->toBeTrue()
        ->and($admin->hasPermissionTo('contacts.delete'))->toBeFalse();
});
