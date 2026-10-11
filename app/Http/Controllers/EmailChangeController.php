<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Users\UserProfileService;
use Illuminate\Http\RedirectResponse;

/** Enlace firmado del correo nuevo (público: el usuario puede no tener sesión). */
class EmailChangeController extends Controller
{
    public function confirm(int $user, string $hash, UserProfileService $profiles): RedirectResponse
    {
        $target = User::query()->withoutGlobalScopes()->findOrFail($user);

        return redirect($profiles->confirmEmailChange($target, $hash)
            ? '/login?email_changed=1'
            : '/login?email_change_failed=1');
    }
}
