<?php

declare(strict_types=1);

namespace App\Services\Mfa;

use App\Enums\AuthEventType;
use App\Models\User;
use App\Notifications\BackupMethodUsedNotification;
use App\Notifications\MfaDisabledNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Request;

/**
 * Avisos por correo de seguridad a quienes administran la cuenta del usuario
 * (permiso `users.manage` en su tenant) y al propio usuario.
 */
class SecurityNotifier
{
    public function backupMethodUsed(User $user, AuthEventType $event): void
    {
        Notification::send($this->recipients($user), new BackupMethodUsedNotification(
            $user->id,
            $user->name,
            $user->email,
            $event,
            Request::ip(),
            now()->timezone('America/Lima')->format('d/m/Y H:i'),
        ));
    }

    public function mfaDisabled(User $user, ?User $actor = null): void
    {
        Notification::send($this->recipients($user), new MfaDisabledNotification(
            $user->id,
            $user->name,
            $user->email,
            $actor !== null && $actor->id !== $user->id ? $actor->name : null,
            Request::ip(),
            now()->timezone('America/Lima')->format('d/m/Y H:i'),
        ));
    }

    /** @return Collection<int, User> */
    public function recipients(User $user): Collection
    {
        $permission = (string) config('mfa.immediate_permission', 'users.manage');
        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId($user->tenant_id);

        try {
            $admins = User::query()->withoutGlobalScopes()
                ->where('tenant_id', $user->tenant_id)
                ->whereNull('deactivated_at')
                ->get()
                ->filter(fn (User $candidate) => $candidate->hasPermissionTo($permission));
        } finally {
            setPermissionsTeamId($previousTeam);
        }

        return $admins->push($user)->unique('id')->values();
    }
}
