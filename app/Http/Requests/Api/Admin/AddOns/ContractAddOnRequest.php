<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\AddOns;

use Illuminate\Foundation\Http\FormRequest;

class ContractAddOnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['add_on_id' => ['required', 'integer', 'exists:add_ons,id']];
    }

    public function attributes(): array
    {
        return ['add_on_id' => 'servicio'];
    }
}
