<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Users;

/**
 * Reglas de los datos personales de un usuario. Se reutilizan en Mi perfil,
 * en la edición desde Usuarios y en el alta de una empresa (con prefijo
 * `admin_`). Fecha de nacimiento, teléfonos, cargo y foto son opcionales.
 */
final class PersonalDataRules
{
    public const PHONE = 'regex:/^\+?[0-9 ()-]{6,20}$/';

    /** @return array<string, list<string>> */
    public static function rules(string $prefix = ''): array
    {
        return [
            "{$prefix}first_name" => ['required', 'string', 'max:80'],
            "{$prefix}last_name" => ['required', 'string', 'max:80'],
            "{$prefix}job_title" => ['nullable', 'string', 'max:100'],
            "{$prefix}birth_date" => ['nullable', 'date_format:Y-m-d', 'before:-16 years', 'after:1900-01-01'],
            "{$prefix}phone" => ['nullable', 'string', self::PHONE],
            "{$prefix}mobile" => ['nullable', 'string', self::PHONE],
        ];
    }

    /** @return list<string> */
    public static function photo(): array
    {
        return ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=96,min_height=96,max_width=4000,max_height=4000'];
    }

    /** @return array<string, string> */
    public static function attributes(string $prefix = ''): array
    {
        return [
            "{$prefix}first_name" => 'nombres',
            "{$prefix}last_name" => 'apellidos',
            "{$prefix}job_title" => 'cargo',
            "{$prefix}birth_date" => 'fecha de nacimiento',
            "{$prefix}phone" => 'teléfono',
            "{$prefix}mobile" => 'móvil',
            "{$prefix}photo" => 'foto',
        ];
    }

    /** @return array<string, string> */
    public static function messages(string $prefix = ''): array
    {
        return [
            "{$prefix}birth_date.before" => 'La persona debe tener al menos 16 años.',
            "{$prefix}phone.regex" => 'Usa solo números, espacios, guiones y el + inicial.',
            "{$prefix}mobile.regex" => 'Usa solo números, espacios, guiones y el + inicial.',
            "{$prefix}photo.dimensions" => 'La foto debe medir entre 96 y 4000 píxeles por lado.',
        ];
    }
}
