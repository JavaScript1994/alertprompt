import { createColumnHelper } from '@tanstack/react-table';
import { BarChart3, Download } from 'lucide-react';
import { useMemo, type ReactNode } from 'react';
import ChannelBadge from '@/components/shared/ChannelBadge';
import DailyDeliveryChart from '@/components/shared/DailyDeliveryChart';
import DataTable from '@/components/shared/DataTable';
import EmptyState from '@/components/shared/EmptyState';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import type { DeliveryCounts, ReportSummary } from '@/types';

export const RANGE_PRESETS = [
    { days: 7, label: '7 días' },
    { days: 30, label: '30 días' },
    { days: 90, label: '90 días' },
] as const;

const CATEGORY_LABELS: Record<string, string> = { marketing: 'Marketing', utility: 'Utilidad', authentication: 'Autenticación' };

export function RangePicker({ days, onChange }: { days: number; onChange: (days: number) => void }) {
    return (
        <fieldset className="inline-flex rounded-lg border bg-card p-0.5">
            <legend className="sr-only">Período</legend>
            {RANGE_PRESETS.map((preset) => (
                <button
                    key={preset.days}
                    type="button"
                    aria-pressed={days === preset.days}
                    onClick={() => onChange(preset.days)}
                    className={cn(
                        'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                        days === preset.days ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground',
                    )}
                >
                    {preset.label}
                </button>
            ))}
        </fieldset>
    );
}

export function ExportButton({ href }: { href: string }) {
    return (
        <Button variant="outline" asChild>
            <a href={href}>
                <Download />
                Exportar CSV
            </a>
        </Button>
    );
}

function Kpi({ label, value, hint }: { label: string; value: string; hint?: string }) {
    return (
        <Card className="gap-1 p-5">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="text-2xl font-semibold text-foreground tabular-nums">{value}</p>
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
        </Card>
    );
}

const n = (value: number) => value.toLocaleString('es-PE');

type CampaignRow = ReportSummary['campaigns'][number];
type BreakdownRow = DeliveryCounts & { key: string; label: ReactNode };
const campaignColumn = createColumnHelper<CampaignRow>();
const breakdownColumn = createColumnHelper<BreakdownRow>();

function Breakdown({ title, description, rows }: { title: string; description?: string; rows: BreakdownRow[] }) {
    const columns = useMemo(
        () => [
            breakdownColumn.accessor('label', { header: '', enableSorting: false, cell: (info) => info.getValue() }),
            breakdownColumn.accessor('sent', { header: 'Enviados', meta: { className: 'text-right tabular-nums' }, cell: (info) => n(info.getValue()) }),
            breakdownColumn.accessor('delivered', { header: 'Entregados', meta: { className: 'text-right tabular-nums' }, cell: (info) => n(info.getValue()) }),
            breakdownColumn.accessor('failed', { header: 'Fallidos', meta: { className: 'text-right tabular-nums' }, cell: (info) => n(info.getValue()) }),
        ],
        [],
    );

    return (
        <div>
            <p className="mb-1 font-semibold text-foreground">{title}</p>
            {description && <p className="mb-3 text-xs text-muted-foreground">{description}</p>}
            {rows.length > 0 ? (
                <DataTable data={rows} columns={columns} minWidth="min-w-[380px]" />
            ) : (
                <p className="text-sm text-muted-foreground">Sin datos en el período.</p>
            )}
        </div>
    );
}

/** Cuerpo común de los reportes (cliente y plataforma). */
export default function ReportView({
    report,
    isLoading,
    showTenant = false,
    extraKpis,
    children,
}: {
    report: ReportSummary | undefined;
    isLoading: boolean;
    showTenant?: boolean;
    extraKpis?: ReactNode;
    children?: ReactNode;
}) {
    const campaignColumns = useMemo(
        () => [
            ...(showTenant ? [campaignColumn.accessor('tenant_name', { header: 'Cliente' })] : []),
            campaignColumn.accessor('name', { header: 'Campaña' }),
            campaignColumn.accessor('channel', { header: 'Canal', cell: (info) => <ChannelBadge channel={info.getValue()} /> }),
            campaignColumn.accessor('category', { header: 'Categoría', cell: (info) => CATEGORY_LABELS[info.getValue()] ?? info.getValue() }),
            campaignColumn.accessor('sent', { header: 'Enviados', meta: { className: 'text-right tabular-nums' }, cell: (info) => n(info.getValue()) }),
            campaignColumn.accessor('delivered', { header: 'Entregados', meta: { className: 'text-right tabular-nums' }, cell: (info) => n(info.getValue()) }),
            campaignColumn.accessor('failed', { header: 'Fallidos', meta: { className: 'text-right tabular-nums' }, cell: (info) => n(info.getValue()) }),
            campaignColumn.accessor('delivery_rate', { header: '% entrega', meta: { className: 'text-right tabular-nums' }, cell: (info) => `${info.getValue()}%` }),
        ],
        [showTenant],
    );

    if (isLoading || !report) return <Skeleton className="h-96 w-full rounded-xl" />;

    const { totals } = report;

    return (
        <div className="space-y-6">
            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <Kpi label="Enviados" value={n(totals.sent)} hint={`${n(totals.attempted)} intentos`} />
                <Kpi label="Entregados" value={n(totals.delivered)} hint={`${totals.delivery_rate}% de lo enviado`} />
                <Kpi label="Fallidos" value={n(totals.failed)} />
                <Kpi label="Omitidos" value={n(totals.skipped)} hint="Sin consentimiento, de baja o sin dato" />
                {extraKpis}
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Entregas por día</CardTitle>
                    <CardDescription>Fecha de envío o del último intento.</CardDescription>
                </CardHeader>
                <CardContent>
                    {totals.attempted > 0 ? (
                        <DailyDeliveryChart days={report.daily} from={report.range.from} to={report.range.to} />
                    ) : (
                        <EmptyState icon={BarChart3} title="Sin envíos en este período" />
                    )}
                </CardContent>
            </Card>

            {children}

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <Breakdown
                    title="Por canal"
                    rows={report.by_channel.map((row) => ({ ...row, key: row.channel, label: <ChannelBadge channel={row.channel} /> }))}
                />
                <Breakdown
                    title="Por categoría de plantilla"
                    description="WhatsApp cobra por mensaje entregado y según la categoría."
                    rows={report.by_category.map((row) => ({ ...row, key: row.category, label: CATEGORY_LABELS[row.category] ?? row.category }))}
                />
            </div>

            <div>
                <p className="mb-3 font-semibold text-foreground">Campañas del período</p>
                {report.campaigns.length > 0 ? (
                    <DataTable data={report.campaigns} columns={campaignColumns} minWidth="min-w-[760px]" />
                ) : (
                    <p className="text-sm text-muted-foreground">Sin campañas con envíos en el período.</p>
                )}
            </div>
        </div>
    );
}
