import { BellOff, CheckCircle2 } from 'lucide-react';
import { useState } from 'react';
import { Link } from 'react-router-dom';
import AlertSeverityBadge from '@/components/shared/AlertSeverityBadge';
import EmptyState from '@/components/shared/EmptyState';
import PageHeader from '@/components/shared/PageHeader';
import Pagination from '@/components/shared/Pagination';
import Tabs from '@/components/shared/Tabs';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useCan } from '@/hooks/usePermissions';
import { useAdminAlerts, useResolveAlert } from '@/hooks/useReports';
import { formatDateTime } from '@/lib/format';

export default function Alerts() {
    const [status, setStatus] = useState<'open' | 'resolved'>('open');
    const [page, setPage] = useState(1);
    const { data, isLoading, isFetching } = useAdminAlerts({ status, page });
    const resolve = useResolveAlert();
    const canManage = useCan()('admin.alerts.manage');

    return (
        <div>
            <PageHeader
                title="Alertas"
                description="Señales de todos los clientes que requieren atención: campañas pausadas, cuentas bloqueadas, calidad baja."
            />

            <Tabs
                tabs={[
                    { key: 'open', label: 'Abiertas' },
                    { key: 'resolved', label: 'Resueltas' },
                ]}
                active={status}
                onChange={(key) => {
                    setStatus(key);
                    setPage(1);
                }}
            />

            {isLoading ? (
                <Skeleton className="h-64 w-full rounded-xl" />
            ) : !data || data.data.length === 0 ? (
                <EmptyState
                    icon={status === 'open' ? CheckCircle2 : BellOff}
                    title={status === 'open' ? 'Sin alertas abiertas' : 'Aún no hay alertas resueltas'}
                />
            ) : (
                <>
                    <div className={isFetching ? 'space-y-3 opacity-60' : 'space-y-3'}>
                        {data.data.map((alert) => (
                            <Card key={alert.id} className="gap-2 p-5">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <AlertSeverityBadge severity={alert.severity} />
                                            {alert.tenant && (
                                                <Link to={`/admin/clients/${alert.tenant.id}`} className="text-sm font-medium text-primary hover:underline dark:text-brand-200">
                                                    {alert.tenant.name}
                                                </Link>
                                            )}
                                            {alert.occurrences > 1 && (
                                                <span className="text-xs text-muted-foreground">×{alert.occurrences}</span>
                                            )}
                                        </div>
                                        <p className="font-semibold text-foreground">{alert.title}</p>
                                        <p className="text-sm text-muted-foreground">{alert.message}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {formatDateTime(alert.updated_at)}
                                            {alert.campaign && ` · Campaña «${alert.campaign.name}»`}
                                            {alert.resolved_at && ` · Resuelta por ${alert.resolved_by ?? 'sistema'} el ${formatDateTime(alert.resolved_at)}`}
                                        </p>
                                    </div>
                                    {status === 'open' && canManage && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            loading={resolve.isPending && resolve.variables === alert.id}
                                            onClick={() => resolve.mutate(alert.id)}
                                        >
                                            <CheckCircle2 />
                                            Marcar resuelta
                                        </Button>
                                    )}
                                </div>
                            </Card>
                        ))}
                    </div>
                    <Pagination meta={data.meta} noun="alertas" onPageChange={setPage} />
                </>
            )}
        </div>
    );
}
