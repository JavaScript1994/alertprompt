<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\User;
use App\Notifications\ConfirmEmailChange;
use App\Notifications\EmailChangedNotice;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Datos personales de los usuarios del panel (no de los contactos). La foto
 * vive en el disco privado y solo se entrega por la API a usuarios con
 * sesión. En la auditoría quedan los campos cambiados, nunca los valores.
 */
class UserProfileService
{
    private const PHOTO_DISK = 'local';

    public const PERSONAL_FIELDS = ['first_name', 'last_name', 'job_title', 'birth_date', 'phone', 'mobile'];

    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function updatePersonalData(User $user, array $data): User
    {
        $user->fill(array_intersect_key($data, array_flip(self::PERSONAL_FIELDS)));
        $changed = array_values(array_intersect(array_keys($user->getDirty()), self::PERSONAL_FIELDS));
        $user->save();

        if ($changed !== []) {
            $this->audit->record('user.profile_updated', $user->tenant_id, $user, ['fields' => $changed]);
        }

        return $user;
    }

    public function setPhoto(User $user, UploadedFile $photo): User
    {
        $previous = $user->photo_path;
        $path = $photo->storeAs(
            "user-photos/{$user->tenant_id}",
            Str::uuid()->toString().'.'.$photo->extension(),
            self::PHOTO_DISK,
        );

        if ($path === false) {
            throw ValidationException::withMessages(['photo' => 'No se pudo guardar la foto.']);
        }

        $user->forceFill(['photo_path' => $path])->save();

        if ($previous !== null) {
            Storage::disk(self::PHOTO_DISK)->delete($previous);
        }

        $this->audit->record('user.photo_updated', $user->tenant_id, $user);

        return $user;
    }

    public function removePhoto(User $user): User
    {
        if ($user->photo_path !== null) {
            Storage::disk(self::PHOTO_DISK)->delete($user->photo_path);
            $user->forceFill(['photo_path' => null])->save();
            $this->audit->record('user.photo_removed', $user->tenant_id, $user);
        }

        return $user;
    }

    public function photoResponse(User $user): StreamedResponse
    {
        $disk = Storage::disk(self::PHOTO_DISK);
        abort_if($user->photo_path === null || ! $disk->exists($user->photo_path), 404);

        return $disk->response($user->photo_path, null, [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * El correo es el usuario de login: el cambio queda pendiente hasta que
     * se confirme desde el correo NUEVO (enlace firmado, 24 h). Mientras
     * tanto se sigue entrando con el actual.
     */
    public function requestEmailChange(User $user, string $email): void
    {
        $user->forceFill(['pending_email' => $email])->save();

        Notification::route('mail', $email)->notify(new ConfirmEmailChange(
            $user->name,
            URL::temporarySignedRoute('email-change.confirm', now()->addDay(), [
                'user' => $user->id,
                'hash' => sha1($email),
            ]),
        ));

        $this->audit->record('user.email_change_requested', $user->tenant_id, $user);
    }

    public function confirmEmailChange(User $user, string $hash): bool
    {
        $pending = $user->pending_email;

        if ($pending === null || ! hash_equals(sha1($pending), $hash)) {
            return false;
        }

        $previous = $user->email;

        $changed = DB::transaction(function () use ($user, $pending) {
            // Otro usuario pudo tomar ese correo mientras tanto.
            if (User::query()->withoutGlobalScopes()->where('email', $pending)->exists()) {
                $user->forceFill(['pending_email' => null])->save();

                return false;
            }

            $user->forceFill([
                'email' => $pending,
                'pending_email' => null,
                'email_verified_at' => now(),
            ])->save();

            return true;
        });

        if ($changed) {
            Notification::route('mail', $previous)->notify(new EmailChangedNotice($user->name, $pending));
            $this->audit->record('user.email_changed', $user->tenant_id, $user);
        }

        return $changed;
    }
}
