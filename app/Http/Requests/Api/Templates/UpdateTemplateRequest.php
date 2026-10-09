<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Templates;

use App\Enums\Channel;
use App\Enums\TemplateCategory;
use App\Enums\TemplateStatus;
use App\Rules\ChannelModuleEnabled;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Tenant activo (en modo soporte es el cliente, no el del usuario).
        $tenantId = TenantContext::id();
        $templateId = $this->route('template')->id;

        return [
            'channel' => ['required', new Enum(Channel::class), new ChannelModuleEnabled],
            'category' => ['required', new Enum(TemplateCategory::class)],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('templates')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($templateId),
            ],
            'body' => ['required', 'string', 'max:4096'],
            'status' => ['sometimes', new Enum(TemplateStatus::class)],
        ];
    }
}
