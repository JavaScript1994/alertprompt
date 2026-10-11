<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\AddOns;

use Illuminate\Foundation\Http\FormRequest;

class RejectAddOnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['note' => ['required', 'string', 'max:500']];
    }

    public function attributes(): array
    {
        return ['note' => 'motivo'];
    }
}
