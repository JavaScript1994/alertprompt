<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Campaigns;

use App\Enums\TemplateStatus;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Tenant activo (en modo soporte es el cliente, no el del usuario).
        $tenantId = TenantContext::id();

        return [
            'name' => ['required', 'string', 'max:255'],
            // Solo plantillas aprobadas: no se puede lanzar una campaña con
            // una plantilla todavía en borrador o pendiente de aprobación.
            'template_id' => [
                'required',
                Rule::exists('templates', 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)->where('status', TemplateStatus::Approved->value)
                ),
            ],
            // Audiencia explícita: nunca "todos los contactos del tenant".
            'contact_ids' => ['required', 'array', 'min:1'],
            'contact_ids.*' => [
                'integer',
                Rule::exists('contacts', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            // Envío único a futuro. Recurrencia (cada hora/día/semana) queda
            // fuera del MVP — ver CLAUDE.md §10.
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
