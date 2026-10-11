<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\AddOns;

use Illuminate\Foundation\Http\FormRequest;

class RequestAddOnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['comment' => 'comentario'];
    }
}
