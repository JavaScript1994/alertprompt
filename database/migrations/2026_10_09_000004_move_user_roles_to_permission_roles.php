<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reemplaza la columna users.role (enum fijo) por roles de Spatie asignados
 * por tenant. Mapea los usuarios existentes para que nadie pierda acceso.
 */
return new class extends Migration
{
    private const LEGACY_MAP = [
        'owner' => 'client-admin',
        'admin' => 'client-admin',
        'member' => 'client-user',
        'viewer' => 'client-viewer',
    ];

    public function up(): void
    {
        Artisan::call('permissions:sync');

        $roleIds = DB::table('roles')->whereNull('tenant_id')->pluck('id', 'name');

        DB::table('users')->select(['id', 'tenant_id', 'role'])->orderBy('id')->each(function (object $user) use ($roleIds) {
            $roleName = self::LEGACY_MAP[$user->role] ?? 'client-user';

            DB::table('model_has_roles')->insertOrIgnore([
                'role_id' => $roleIds[$roleName],
                'model_type' => User::class,
                'model_id' => $user->id,
                'tenant_id' => $user->tenant_id,
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('member')->after('password');
        });

        $reverse = ['client-admin' => 'admin', 'client-user' => 'member', 'client-viewer' => 'viewer'];

        DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->whereIn('roles.name', array_keys($reverse))
            ->get(['model_has_roles.model_id', 'roles.name'])
            ->each(fn (object $row) => DB::table('users')
                ->where('id', $row->model_id)
                ->update(['role' => $reverse[$row->name]]));
    }
};
