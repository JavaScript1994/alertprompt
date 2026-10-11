<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Users;

use Illuminate\Foundation\Http\FormRequest;

class UploadPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['photo' => ['required', ...PersonalDataRules::photo()]];
    }

    public function attributes(): array
    {
        return PersonalDataRules::attributes();
    }

    public function messages(): array
    {
        return PersonalDataRules::messages();
    }
}
