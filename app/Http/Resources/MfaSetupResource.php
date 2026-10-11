<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Datos para enrolar: QR y secreto. Se muestran UNA vez; nunca se vuelven a
 * entregar (un enrolamiento nuevo genera otro secreto).
 *
 * @property array{secret: string, otpauth_url: string, qr_svg: string} $resource
 */
class MfaSetupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'secret' => $this->resource['secret'],
            'otpauth_url' => $this->resource['otpauth_url'],
            'qr_svg' => $this->resource['qr_svg'],
        ];
    }
}
