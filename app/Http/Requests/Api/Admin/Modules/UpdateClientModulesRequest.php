<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\Modules;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientModulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'modules' => ['present', 'array'],
            'modules.*' => ['string', 'distinct', Rule::in(array_keys(config('modules.catalog')))],
        ];
    }
}
