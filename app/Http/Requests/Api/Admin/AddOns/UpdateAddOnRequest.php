<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\AddOns;

use App\Enums\TenantAddOnStatus;
use App\Models\AddOn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAddOnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $addOn = $this->route('addOn');

        return AddOnRules::rules($addOn instanceof AddOn ? $addOn->id : null);
    }

    public function attributes(): array
    {
        return AddOnRules::attributes();
    }

    /** Con clientes que lo tienen contratado, no cambia qué módulo prende ni deja de estar disponible. */
    public function after(): array
    {
        return [function (Validator $validator) {
            $addOn = $this->route('addOn');
            if (! $addOn instanceof AddOn || $validator->errors()->isNotEmpty()) {
                return;
            }

            $contracted = $addOn->contracts()->where('status', TenantAddOnStatus::Active)->exists();
            if ($contracted && ($this->input('module') !== $addOn->module || $this->input('status') !== $addOn->status->value)) {
                $validator->errors()->add('module', 'Hay clientes con este servicio contratado: no se puede cambiar su módulo ni su estado.');
            }
        }];
    }
}
