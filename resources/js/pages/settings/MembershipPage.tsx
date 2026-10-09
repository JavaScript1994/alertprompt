import { FileText } from 'lucide-react';
import { MembershipStatusBadge, UsageMeters, formatDate, formatPrice } from '@/components/shared/MembershipBits';
import EmptyState from '@/components/shared/EmptyState';
import PageHeader from '@/components/shared/PageHeader';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useMyMembership } from '@/hooks/useMemberships';

export default function MembershipPage() {
    const { data, isLoading } = useMyMembership();

    if (isLoading || !data) return <Skeleton className="h-96 w-full rounded-xl" />;

    const { current, next, usage, history } = data;

    return (
        <div>
            <PageHeader title="Membresía" description="Tu contrato con AlertPrompt y el consumo del mes." />

            {!current ? (
                <EmptyState
                    icon={FileText}
                    title="No tienes una membresía vigente"
                    description="Comunícate con AlertPrompt para activar o renovar tu plan."
                />
            ) : (
                <div className="grid grid-cols-1 gap-6 xl:grid-cols-12">
                    <Card className="xl:col-span-5">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 capitalize">
                                Plan {current.plan}
                                <MembershipStatusBadge status={current.status} />
                            </CardTitle>
                            <CardDescription>{formatPrice(current)}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <div>
                                <p className="text-xs text-muted-foreground">Vigencia</p>
                                <p className="font-medium">
                                    {formatDate(current.starts_at)} – {formatDate(current.ends_at)}
                                </p>
                            </div>
                            {current.contract_reference && (
                                <div>
                                    <p className="text-xs text-muted-foreground">Contrato</p>
                                    <p className="font-medium">{current.contract_reference}</p>
                                </div>
                            )}
                            {next && (
                                <Alert variant="info">
                                    <AlertDescription>
                                        Renovación programada: plan <span className="capitalize">{next.plan}</span> desde el{' '}
                                        {formatDate(next.starts_at)}.
                                    </AlertDescription>
                                </Alert>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="xl:col-span-7">
                        <CardHeader>
                            <CardTitle>Consumo de este mes</CardTitle>
                            <CardDescription>
                                Mensajes enviados.{' '}
                                {data.quotas_enforced ? 'Al llegar a la cuota no se pueden iniciar más campañas del canal.' : 'Las cuotas son de referencia.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <UsageMeters usage={usage} />
                        </CardContent>
                    </Card>
                </div>
            )}

            {history.length > 1 && (
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle>Historial</CardTitle>
                    </CardHeader>
                    <CardContent className="divide-y p-0">
                        {history.map((membership) => (
                            <div key={membership.id} className="flex flex-wrap items-center justify-between gap-3 px-6 py-3 text-sm">
                                <span className="capitalize">Plan {membership.plan}</span>
                                <span className="text-muted-foreground">
                                    {formatDate(membership.starts_at)} – {formatDate(membership.ends_at)}
                                </span>
                                <MembershipStatusBadge status={membership.status} />
                            </div>
                        ))}
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
