<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Campaigns;

use App\Enums\TemplateStatus;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignRequest extends FormRequest
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
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Ingresa el nombre de la campaña.',
            'template_id.required' => 'Elegí una plantilla.',
            'template_id.exists' => 'La plantilla elegida no está aprobada o no existe.',
            'contact_ids.required' => 'Elegí al menos un contacto.',
            'contact_ids.min' => 'Elegí al menos un contacto.',
            'scheduled_at.after' => 'La fecha programada debe ser en el futuro.',
        ];
    }
}
