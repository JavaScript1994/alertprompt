<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\Channel;
use App\Services\Modules\TenantModules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** El canal elegido debe estar contratado por el tenant activo. */
class ChannelModuleEnabled implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $channel = $value instanceof Channel ? $value : Channel::tryFrom((string) $value);

        if ($channel !== null && ! app(TenantModules::class)->channelEnabled($channel)) {
            $fail("Tu plan no incluye el canal {$channel->label()}.");
        }
    }
}
