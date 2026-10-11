<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin\Modules;

use App\Models\Tenant;
use App\Services\AddOns\AddOnManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

    /** Un módulo contratado como servicio adicional se apaga cancelando el servicio. */
    public function after(): array
    {
        return [function (Validator $validator) {
            $client = $this->route('client');
            if (! $client instanceof Tenant || $validator->errors()->isNotEmpty()) {
                return;
            }

            $missing = array_diff(app(AddOnManager::class)->contractedModules($client->id), (array) $this->input('modules'));
            foreach ($missing as $module) {
                $label = config("modules.catalog.{$module}.label", $module);
                $validator->errors()->add('modules', "«{$label}» está contratado como servicio adicional. Cancela el servicio para desactivarlo.");
            }
        }];
    }
}
