<?php

declare(strict_types=1);

namespace App\Services\BulkImports;

use App\Enums\Channel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Una fila del CSV de carga masiva ya interpretada. La evidencia de
 * consentimiento cuenta solo si está COMPLETA: canales válidos, fecha pasada,
 * origen y texto aceptado. Si viene a medias, el contacto se importa sin
 * consentimiento y la fila se reporta.
 */
final class BulkContactRow
{
    public const CONSENT_COLUMNS = ['consent_channels', 'consent_granted_at', 'consent_source', 'consent_evidence_text', 'consent_ip'];

    /**
     * @param  list<Channel>  $consentChannels
     * @param  array<string, string>  $attributes
     */
    private function __construct(
        public readonly string $name,
        public readonly ?string $phone,
        public readonly ?string $email,
        public readonly array $attributes,
        public readonly array $consentChannels,
        public readonly ?CarbonImmutable $consentGrantedAt,
        public readonly ?string $consentSource,
        public readonly ?string $consentEvidence,
        public readonly ?string $consentIp,
        public readonly ?string $consentProblem,
    ) {}

    /** @param  array<string, string>  $data  columnas en minúscula */
    public static function fromCsv(array $data): self
    {
        $value = fn (string $key) => ($v = trim((string) ($data[$key] ?? ''))) === '' ? null : $v;

        $rawChannels = $value('consent_channels');
        $rawDate = $value('consent_granted_at');
        $source = $value('consent_source');
        $evidence = $value('consent_evidence_text');

        $channels = [];
        $problem = null;
        $grantedAt = null;

        $anyConsentField = $rawChannels !== null || $rawDate !== null || $source !== null || $evidence !== null;

        if ($anyConsentField) {
            foreach (preg_split('/[|,;\s]+/', (string) $rawChannels, flags: PREG_SPLIT_NO_EMPTY) as $raw) {
                $channel = Channel::tryFrom(mb_strtolower($raw));
                if ($channel === null) {
                    $problem = "Canal de consentimiento desconocido: {$raw}.";
                    break;
                }
                $channels[] = $channel;
            }

            try {
                $grantedAt = $rawDate !== null ? CarbonImmutable::parse($rawDate) : null;
            } catch (Throwable) {
                $grantedAt = null;
            }

            $problem ??= match (true) {
                $channels === [] => 'Falta el canal del consentimiento.',
                $grantedAt === null => 'Fecha de consentimiento ausente o inválida.',
                $grantedAt->isFuture() => 'La fecha de consentimiento está en el futuro.',
                $source === null => 'Falta el origen del consentimiento.',
                $evidence === null => 'Falta el texto que aceptó el contacto.',
                default => null,
            };
        }

        $complete = $anyConsentField && $problem === null;

        return new self(
            name: (string) $value('name'),
            phone: $value('phone'),
            email: $value('email') !== null ? mb_strtolower((string) $value('email')) : null,
            attributes: Arr::except($data, ['name', 'phone', 'email', ...self::CONSENT_COLUMNS]),
            consentChannels: $complete ? array_values(array_unique($channels, SORT_REGULAR)) : [],
            consentGrantedAt: $complete ? $grantedAt : null,
            consentSource: $complete ? $source : null,
            consentEvidence: $complete ? $evidence : null,
            consentIp: $complete ? $value('consent_ip') : null,
            consentProblem: $problem,
        );
    }

    public function contactProblem(): ?string
    {
        if ($this->name === '') {
            return 'Falta el nombre.';
        }
        if ($this->phone === null && $this->email === null) {
            return 'Falta teléfono o email.';
        }
        if ($this->phone !== null && ! preg_match('/^\+[1-9]\d{7,14}$/', $this->phone)) {
            return "Teléfono no está en formato internacional: {$this->phone}.";
        }
        if ($this->email !== null && filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            return "Email inválido: {$this->email}.";
        }

        return null;
    }

    public function identifierFor(Channel $channel): ?string
    {
        return $channel === Channel::Email ? $this->email : $this->phone;
    }
}
