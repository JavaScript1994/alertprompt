<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\AddOns;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddOnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return AddOnRules::rules();
    }

    public function attributes(): array
    {
        return AddOnRules::attributes();
    }
}
