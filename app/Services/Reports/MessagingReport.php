<?php

declare(strict_types=1);

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Métricas de envío por período. La fecha de cada mensaje es la del envío
 * (o del último intento, si falló o se omitió); los pendientes y en cola no
 * cuentan. Consultas portables (Postgres en producción, SQLite en tests).
 *
 * Meta cobra por mensaje ENTREGADO y según la categoría de la plantilla
 * (CLAUDE.md §4-bis): por eso se reporta entregados por categoría.
 */
class MessagingReport
{
    private const SENT = "('sent','delivered','read')";

    private const DELIVERED = "('delivered','read')";

    /** null = todos los clientes (reporte de la plataforma). */
    public function __construct(
        private readonly ?int $tenantId,
        private readonly CarbonImmutable $from,
        private readonly CarbonImmutable $to,
    ) {}

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return [
            'range' => ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString()],
            'totals' => $this->totals(),
            'daily' => $this->daily(),
            'by_channel' => $this->grouped('c.channel', 'channel'),
            'by_category' => $this->grouped('t.category', 'category'),
            'campaigns' => $this->campaigns(),
        ];
    }

    /** @return array<string, int|float> */
    public function totals(): array
    {
        $row = $this->base()->selectRaw($this->counts())->first();

        return $this->normalize((array) $row);
    }

    /** @return list<array<string, mixed>> */
    public function daily(): array
    {
        $day = 'DATE('.$this->activityAt().')';

        return $this->base()
            ->selectRaw("{$day} as day, ".$this->counts())
            ->groupByRaw($day)
            ->orderByRaw($day)
            ->get()
            ->map(fn ($row) => ['day' => (string) $row->day, ...$this->normalize((array) $row)])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function campaigns(int $limit = 50): array
    {
        return $this->base()
            ->join('tenants as tn', 'tn.id', '=', 'c.tenant_id')
            ->selectRaw('c.id, c.name, c.channel, c.status, t.category, tn.name as tenant_name, '.$this->counts())
            ->groupBy('c.id', 'c.name', 'c.channel', 'c.status', 't.category', 'tn.name')
            ->orderByDesc('c.id')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'channel' => $row->channel,
                'status' => $row->status,
                'category' => $row->category,
                'tenant_name' => $row->tenant_name,
                ...$this->normalize((array) $row),
            ])
            ->all();
    }

    /** Totales por cliente (solo reporte de plataforma). @return list<array<string, mixed>> */
    public function byTenant(): array
    {
        return $this->base()
            ->join('tenants as tn', 'tn.id', '=', 'c.tenant_id')
            ->selectRaw('tn.id as tenant_id, tn.name as tenant_name, tn.type as tenant_type, '.$this->counts())
            ->groupBy('tn.id', 'tn.name', 'tn.type')
            ->orderByDesc('attempted')
            ->get()
            ->map(fn ($row) => [
                'tenant_id' => (int) $row->tenant_id,
                'tenant_name' => $row->tenant_name,
                'tenant_type' => $row->tenant_type,
                ...$this->normalize((array) $row),
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function grouped(string $column, string $key): array
    {
        return $this->base()
            ->selectRaw("{$column} as grp, ".$this->counts())
            ->groupBy($column)
            ->orderBy($column)
            ->get()
            ->map(fn ($row) => [$key => $row->grp, ...$this->normalize((array) $row)])
            ->all();
    }

    private function base(): Builder
    {
        return DB::table('campaign_recipients as r')
            ->join('campaigns as c', 'c.id', '=', 'r.campaign_id')
            ->join('templates as t', 't.id', '=', 'c.template_id')
            ->when($this->tenantId !== null, fn (Builder $q) => $q->where('c.tenant_id', $this->tenantId))
            ->whereNotIn('r.status', ['pending', 'queued'])
            ->whereRaw($this->activityAt().' >= ?', [$this->from->startOfDay()->toDateTimeString()])
            ->whereRaw($this->activityAt().' <= ?', [$this->to->endOfDay()->toDateTimeString()]);
    }

    private function activityAt(): string
    {
        return 'COALESCE(r.sent_at, r.updated_at)';
    }

    private function counts(): string
    {
        return implode(', ', [
            'COUNT(*) as attempted',
            'SUM(CASE WHEN r.status IN '.self::SENT.' THEN 1 ELSE 0 END) as sent',
            'SUM(CASE WHEN r.status IN '.self::DELIVERED.' THEN 1 ELSE 0 END) as delivered',
            "SUM(CASE WHEN r.status = 'read' THEN 1 ELSE 0 END) as read_count",
            "SUM(CASE WHEN r.status = 'failed' THEN 1 ELSE 0 END) as failed",
            "SUM(CASE WHEN r.status = 'skipped' THEN 1 ELSE 0 END) as skipped",
        ]);
    }

    /** @param  array<string, mixed>  $row @return array<string, int|float> */
    private function normalize(array $row): array
    {
        $sent = (int) ($row['sent'] ?? 0);
        $delivered = (int) ($row['delivered'] ?? 0);

        return [
            'attempted' => (int) ($row['attempted'] ?? 0),
            'sent' => $sent,
            'delivered' => $delivered,
            'read' => (int) ($row['read_count'] ?? 0),
            'failed' => (int) ($row['failed'] ?? 0),
            'skipped' => (int) ($row['skipped'] ?? 0),
            'delivery_rate' => $sent > 0 ? round($delivered / $sent * 100, 1) : 0.0,
        ];
    }
}
