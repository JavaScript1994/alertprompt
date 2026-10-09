<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/** Rango de fechas de un reporte: por defecto los últimos 30 días, máximo un año. */
class ReportRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function from(): CarbonImmutable
    {
        return $this->filled('from') ? CarbonImmutable::parse($this->input('from')) : $this->to()->subDays(29);
    }

    public function to(): CarbonImmutable
    {
        return $this->filled('to') ? CarbonImmutable::parse($this->input('to')) : CarbonImmutable::today();
    }

    protected function passedValidation(): void
    {
        if ($this->from()->diffInDays($this->to()) > 366) {
            abort(422, 'El rango máximo es de un año.');
        }
    }
}
