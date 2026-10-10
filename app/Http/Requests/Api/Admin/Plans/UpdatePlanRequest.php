<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\Plans;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Plan $plan */
        $plan = $this->route('plan');

        return [
            ...PlanRules::rules(),
            'name' => ['required', 'string', 'max:60', Rule::unique('plans', 'name')->ignore($plan->id)],
        ];
    }

    public function attributes(): array
    {
        return PlanRules::attributes();
    }
}
