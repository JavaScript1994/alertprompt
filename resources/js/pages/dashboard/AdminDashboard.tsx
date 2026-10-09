import { AlertOctagon, ArrowRight, Building2, Receipt, Send, TriangleAlert } from 'lucide-react';
import { Link } from 'react-router-dom';
import AlertSeverityBadge from '@/components/shared/AlertSeverityBadge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useAuthUser } from '@/hooks/useAuth';
import { useAdminInvoices } from '@/hooks/useBilling';
import { useCan } from '@/hooks/usePermissions';
import { lastDays, useAdminAlerts, useAdminReport } from '@/hooks/useReports';
import { formatDateTime, initials } from '@/lib/format';

const n = (value: number | undefined) => (value === undefined ? '—' : value.toLocaleString('es-PE'));

function Kpi({ icon: Icon, label, value, hint, to }: { icon: typeof Send; label: string; value: string; hint?: string; to: string }) {
    return (
        <Link to={to} className="group">
            <Card className="h-full gap-2 p-5 transition-shadow group-hover:shadow-lg">
                <div className="flex items-center justify-between">
                    <p className="text-xs text-muted-foreground">{label}</p>
                    <Icon className="size-4 text-muted-foreground" />
                </div>
                <p className="text-2xl font-semibold tabular-nums">{value}</p>
                {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            </Card>
        </Link>
    );
}

/** Inicio del administrador general: estado de todos los clientes, no de una cuenta. */
export default function AdminDashboard() {
    const { data: user } = useAuthUser();
    const can = useCan();
    const range = lastDays(30);
    const { data: report, isLoading } = useAdminReport(range);
    const { data: alerts } = useAdminAlerts({ status: 'open', page: 1 });
    const { data: overdue } = useAdminInvoices({ page: 1, status: 'overdue' });

    return (
        <div className="grid grid-cols-12 gap-6">
            <div className="col-span-12 flex items-center gap-4 rounded-xl bg-lightsecondary p-6">
                <Avatar className="size-12">
                    <AvatarFallback className="bg-card text-sm">{initials(user?.name)}</AvatarFallback>
                </Avatar>
                <div className="min-w-0">
                    <h1 className="text-lg">¡Hola, {user?.name?.split(' ')[0] ?? ''}!</h1>
                    <p className="text-muted-foreground">Así están tus clientes en los últimos 30 días.</p>
                </div>
            </div>

            {isLoading ? (
                <Skeleton className="col-span-12 h-28 rounded-xl" />
            ) : (
                <div className="col-span-12 grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
                    <Kpi
                        icon={Building2}
                        label="Clientes activos"
                        value={n(report?.clients.active)}
                        hint={`${n(report?.clients.suspended)} suspendidos`}
                        to="/admin/clients/companies"
                    />
                    <Kpi
                        icon={Send}
                        label="Mensajes enviados"
                        value={n(report?.totals.sent)}
                        hint={`${report?.totals.delivery_rate ?? 0}% entregados`}
                        to="/admin/reports"
                    />
                    <Kpi icon={TriangleAlert} label="Alertas abiertas" value={n(report?.open_alerts)} to="/admin/alerts" />
                    <Kpi
                        icon={Receipt}
                        label="Comprobantes vencidos"
                        value={n(overdue?.meta.total)}
                        to="/admin/invoices"
                    />
                </div>
            )}

            <Card className="col-span-12 gap-0 p-0 xl:col-span-7">
                <CardHeader className="flex-row items-center justify-between border-b px-6 py-4">
                    <CardTitle className="text-base">Alertas que requieren atención</CardTitle>
                    {can('admin.alerts.view') && (
                        <Button asChild variant="link" size="sm">
                            <Link to="/admin/alerts">
                                Ver todas <ArrowRight />
                            </Link>
                        </Button>
                    )}
                </CardHeader>
                <CardContent className="divide-y p-0">
                    {alerts && alerts.data.length > 0 ? (
                        alerts.data.slice(0, 5).map((alert) => (
                            <div key={alert.id} className="flex items-start gap-3 px-6 py-3">
                                <AlertSeverityBadge severity={alert.severity} />
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium">{alert.title}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {alert.tenant?.name} · {formatDateTime(alert.updated_at, 'short')}
                                    </p>
                                </div>
                            </div>
                        ))
                    ) : (
                        <p className="flex items-center gap-2 px-6 py-6 text-sm text-muted-foreground">
                            <AlertOctagon className="size-4" /> Sin alertas abiertas.
                        </p>
                    )}
                </CardContent>
            </Card>

            <Card className="col-span-12 gap-0 p-0 xl:col-span-5">
                <CardHeader className="border-b px-6 py-4">
                    <CardTitle className="text-base">Clientes con más envíos</CardTitle>
                </CardHeader>
                <CardContent className="divide-y p-0">
                    {report && report.by_tenant.length > 0 ? (
                        report.by_tenant.slice(0, 5).map((row) => (
                            <Link
                                key={row.tenant_id}
                                to={`/admin/clients/${row.tenant_id}`}
                                className="flex items-center justify-between gap-3 px-6 py-3 text-sm hover:bg-muted/50"
                            >
                                <span className="truncate font-medium">{row.tenant_name}</span>
                                <span className="shrink-0 text-muted-foreground tabular-nums">
                                    {n(row.sent)} enviados · {row.delivery_rate}%
                                </span>
                            </Link>
                        ))
                    ) : (
                        <p className="px-6 py-6 text-sm text-muted-foreground">Sin envíos en los últimos 30 días.</p>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
