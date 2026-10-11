<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\Announcements;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementType;
use App\Models\AddOn;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(AnnouncementType::class)],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:2000'],
            // Ruta interna del panel o enlace https (nunca javascript: ni http).
            'link_url' => ['nullable', 'string', 'max:500', function (string $attribute, mixed $value, Closure $fail) {
                if (! preg_match('#^(/[A-Za-z0-9/_\-?=&.]*|https://[^\s]+)$#', (string) $value)) {
                    $fail('Usa una ruta del panel (/settings/membership) o un enlace que empiece con https://.');
                }
                // Enlace a un servicio adicional (promociones): el servicio debe existir y ofrecerse.
                if (preg_match('#^/settings/services\?service=([a-z0-9-]+)$#', (string) $value, $match)
                    && ! AddOn::query()->active()->where('key', $match[1])->exists()) {
                    $fail('Ese servicio no existe o está desactivado.');
                }
            }],
            'link_label' => ['nullable', 'required_with:link_url', 'string', 'max:40'],
            'audience' => ['required', Rule::enum(AnnouncementAudience::class)],
            'plan_keys' => ['exclude_unless:audience,plans', 'required', 'array', 'min:1'],
            'plan_keys.*' => ['string', Rule::exists('plans', 'key')],
            'tenant_ids' => ['exclude_unless:audience,tenants', 'required', 'array', 'min:1'],
            'tenant_ids.*' => ['integer', Rule::exists('tenants', 'id')->where(fn ($q) => $q->where('is_platform', false))],
            'admins_only' => ['boolean'],
            // null = borrador.
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:published_at'],
            'send_email' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'tipo', 'title' => 'título', 'body' => 'mensaje', 'link_url' => 'enlace', 'link_label' => 'texto del botón',
            'audience' => 'destinatarios', 'plan_keys' => 'planes', 'tenant_ids' => 'clientes',
            'published_at' => 'publicación', 'expires_at' => 'vencimiento',
        ];
    }

    public function messages(): array
    {
        return [
            'plan_keys.required' => 'Elige al menos un plan.',
            'tenant_ids.required' => 'Elige al menos un cliente.',
            'expires_at.after' => 'El vencimiento debe ser posterior a la publicación.',
        ];
    }
}
