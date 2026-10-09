import { createColumnHelper } from '@tanstack/react-table';
import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import DataTable from '@/components/shared/DataTable';
import PageHeader from '@/components/shared/PageHeader';
import ReportView, { ExportButton, RangePicker } from '@/components/shared/ReportView';
import { Card } from '@/components/ui/card';
import { useCan } from '@/hooks/usePermissions';
import { lastDays, useAdminReport } from '@/hooks/useReports';
import type { AdminReportSummary } from '@/types';

type TenantRow = AdminReportSummary['by_tenant'][number];
const column = createColumnHelper<TenantRow>();
const n = (value: number) => value.toLocaleString('es-PE');

export default function AdminReports() {
    const [days, setDays] = useState(30);
    const range = lastDays(days);
    const { data, isLoading } = useAdminReport(range);
    const canExport = useCan()('admin.reports.export');

    const columns = useMemo(
        () => [
            column.accessor('tenant_name', {
                header: 'Cliente',
                cell: (info) => (
                    <Link to={`/admin/clients/${info.row.original.tenant_id}`} className="font-medium text-foreground hover:text-primary">
                        {info.getValue()}
                    </Link>
                ),
            }),
            column.accessor('sent', { header: 'Enviados', meta: { className: 'text-right tabular-nums' }, cell: (info) => n(info.getValue()) }),
            column.accessor('delivered', { header: 'Entregados', meta: { className: 'text-right tabular-nums' }, cell: (info) => n(info.getValue()) }),
            column.accessor('failed', { header: 'Fallidos', meta: { className: 'text-right tabular-nums' }, cell: (info) => n(info.getValue()) }),
            column.accessor('delivery_rate', { header: '% entrega', meta: { className: 'text-right tabular-nums' }, cell: (info) => `${info.getValue()}%` }),
        ],
        [],
    );

    return (
        <div>
            <PageHeader
                title="Reportes globales"
                description="Envíos de todos los clientes."
                actions={
                    <>
                        <RangePicker days={days} onChange={setDays} />
                        {canExport && <ExportButton href={`/api/admin/reports/export?from=${range.from}&to=${range.to}`} />}
                    </>
                }
            />
            <ReportView
                report={data}
                isLoading={isLoading}
                showTenant
                extraKpis={
                    data && (
                        <>
                            <Card className="gap-1 p-5">
                                <p className="text-xs text-muted-foreground">Clientes activos</p>
                                <p className="text-2xl font-semibold tabular-nums">{n(data.clients.active)}</p>
                                <p className="text-xs text-muted-foreground">{n(data.clients.suspended)} suspendidos</p>
                            </Card>
                            <Card className="gap-1 p-5">
                                <p className="text-xs text-muted-foreground">Alertas abiertas</p>
                                <p className="text-2xl font-semibold tabular-nums">{n(data.open_alerts)}</p>
                                <Link to="/admin/alerts" className="text-xs text-primary hover:underline dark:text-brand-200">
                                    Ver alertas
                                </Link>
                            </Card>
                        </>
                    )
                }
            >
                <div>
                    <p className="mb-3 font-semibold text-foreground">Por cliente</p>
                    {data && data.by_tenant.length > 0 ? (
                        <DataTable data={data.by_tenant} columns={columns} minWidth="min-w-[560px]" />
                    ) : (
                        <p className="text-sm text-muted-foreground">Sin envíos en el período.</p>
                    )}
                </div>
            </ReportView>
        </div>
    );
}
