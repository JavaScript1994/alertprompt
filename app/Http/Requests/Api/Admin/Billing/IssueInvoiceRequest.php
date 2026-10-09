<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\Billing;

use Illuminate\Foundation\Http\FormRequest;

class IssueInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:250'],
            'subtotal' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
        ];
    }

    public function attributes(): array
    {
        return ['description' => 'descripción', 'subtotal' => 'monto sin IGV'];
    }
}
