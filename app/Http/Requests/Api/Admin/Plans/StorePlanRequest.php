<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\Plans;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...PlanRules::rules(),
            'name' => ['required', 'string', 'max:60', Rule::unique('plans', 'name')],
        ];
    }

    public function attributes(): array
    {
        return PlanRules::attributes();
    }
}
