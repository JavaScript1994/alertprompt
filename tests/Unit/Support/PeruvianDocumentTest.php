<?php

declare(strict_types=1);

use App\Enums\DocumentType;
use App\Enums\TenantType;
use App\Support\PeruvianDocument;

it('validates RUC check digits', function (string $ruc, bool $valid) {
    expect(PeruvianDocument::isValidRuc($ruc))->toBe($valid);
})->with([
    'SUNAT' => ['20131312955', true],
    'empresa' => ['20100070970', true],
    'persona natural' => ['10467793549', true],
    'dígito errado' => ['20131312956', false],
    'prefijo inválido' => ['30131312955', false],
    'corto' => ['2013131295', false],
]);

it('only accepts RUC 20 for companies and 10/15/17 for individuals', function () {
    expect(PeruvianDocument::isValidRuc('20131312955', TenantType::Company))->toBeTrue()
        ->and(PeruvianDocument::isValidRuc('10467793549', TenantType::Company))->toBeFalse()
        ->and(PeruvianDocument::isValidRuc('10467793549', TenantType::Individual))->toBeTrue()
        ->and(PeruvianDocument::isValidRuc('20131312955', TenantType::Individual))->toBeFalse();
});

it('validates DNI and CE formats', function () {
    expect(PeruvianDocument::isValid(DocumentType::Dni, '46779354'))->toBeTrue()
        ->and(PeruvianDocument::isValid(DocumentType::Dni, '4677935'))->toBeFalse()
        ->and(PeruvianDocument::isValid(DocumentType::Ce, '001234567'))->toBeTrue()
        ->and(PeruvianDocument::isValid(DocumentType::Ce, '12'))->toBeFalse();
});
