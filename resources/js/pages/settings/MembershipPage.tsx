import { CheckCircle2, Clock, FileText, History, Send, XCircle } from 'lucide-react';
import { useState } from 'react';
import EmptyState from '@/components/shared/EmptyState';
import { MembershipStatusBadge, UsageMeters, formatDate, formatPrice } from '@/components/shared/MembershipBits';
import PageHeader from '@/components/shared/PageHeader';
import PlanCards, { planPrice } from '@/components/shared/PlanCards';
import Tabs, { type TabItem } from '@/components/shared/Tabs';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { useAuthUser } from '@/hooks/useAuth';
import { useCancelPlanChange, useMyMembership, useRequestPlanChange } from '@/hooks/useMemberships';
import { useCan } from '@/hooks/usePermissions';
import { apiErrorMessage } from '@/lib/format';
import type { Plan } from '@/types';

function RequestDialog({ plan, currentName, onClose }: { plan: Plan; currentName: string | null; onClose: () => void }) {
    const request = useRequestPlanChange();
    const [comment, setComment] = useState('');

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Solicitar el plan {plan.name}</DialogTitle>
                    <DialogDescription>
                        {currentName ? `Hoy tienes el plan ${currentName}. ` : ''}AlertPrompt revisará tu solicitud y te confirmará
                        desde cuándo aplica el cambio ({planPrice(plan)}
                        {plan.monthly_price !== null && ' / mes + IGV'}).
                    </DialogDescription>
                </DialogHeader>
                {request.isError && (
                    <Alert variant="error">
                        <XCircle />
                        <AlertTitle>{apiErrorMessage(request.error, ['plan', 'comment'], 'No se pudo enviar la solicitud.')}</AlertTitle>
                    </Alert>
                )}
                <Field label="Comentario (opcional)" htmlFor="plan-change-comment" hint="Ej.: necesitamos más mensajes de WhatsApp desde el próximo mes.">
                    <Textarea id="plan-change-comment" rows={3} value={comment} onChange={(event) => setComment(event.target.value)} />
                </Field>
                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button
                        loading={request.isPending}
                        onClick={() => request.mutate({ plan: plan.key, comment: comment.trim() || null }, { onSuccess: onClose })}
                    >
                        <Send />
                        Enviar solicitud
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

type TabKey = 'membership' | 'plans' | 'history';

export default function MembershipPage() {
    const { data, isLoading } = useMyMembership();
    const { data: user } = useAuthUser();
    const cancelChange = useCancelPlanChange();
    // En modo soporte no se solicita en nombre del cliente (el backend lo bloquea).
    const canRequest = useCan()('membership.request_change') && !user?.impersonating;
    const [requesting, setRequesting] = useState<Plan | null>(null);
    const [tab, setTab] = useState<TabKey>('membership');

    if (isLoading || !data) return <Skeleton className="h-96 w-full rounded-xl" />;

    const { current, next, usage, history, plans, pending_request: pending, last_decision: decision } = data;

    const tabs: TabItem<TabKey>[] = [
        { key: 'membership', label: 'Mi membresía' },
        ...(plans.length > 0 ? ([{ key: 'plans', label: 'Planes' }] as TabItem<TabKey>[]) : []),
        { key: 'history', label: 'Historial' },
    ];

    return (
        <div>
            <PageHeader title="Membresía" description="Tu contrato con AlertPrompt y el consumo del mes." />

            <Tabs tabs={tabs} active={tab} onChange={setTab} />

            {tab === 'membership' && (
                <>
                    {pending && (
                        <Alert variant="warning" className="mb-6">
                            <Clock />
                            <AlertTitle>Solicitud de cambio al plan {pending.requested_plan_name} en revisión</AlertTitle>
                            <AlertDescription className="flex flex-wrap items-center justify-between gap-3">
                                <span>
                                    Enviada el {formatDate(pending.created_at.slice(0, 10))}
                                    {pending.requested_by && ` por ${pending.requested_by}`}. Te avisaremos cuando AlertPrompt la
                                    apruebe.
                                </span>
                                {canRequest && (
                                    <Button variant="outline" size="sm" loading={cancelChange.isPending} onClick={() => cancelChange.mutate()}>
                                        Cancelar solicitud
                                    </Button>
                                )}
                            </AlertDescription>
                        </Alert>
                    )}

                    {!pending && decision?.status === 'rejected' && (
                        <Alert variant="error" className="mb-6">
                            <XCircle />
                            <AlertTitle>Tu solicitud de cambio al plan {decision.requested_plan_name} no fue aprobada</AlertTitle>
                            {decision.decision_note && <AlertDescription>Motivo: {decision.decision_note}</AlertDescription>}
                        </Alert>
                    )}

                    {!pending && decision?.status === 'approved' && decision.effective_from && next && (
                        <Alert variant="success" className="mb-6">
                            <CheckCircle2 />
                            <AlertTitle>
                                Cambio al plan {decision.requested_plan_name} aprobado desde el {formatDate(decision.effective_from)}
                            </AlertTitle>
                        </Alert>
                    )}

                    {!current ? (
                        <EmptyState
                            icon={FileText}
                            title="Tu membresía empieza pronto"
                            description={next ? `El plan ${next.plan_name} empieza el ${formatDate(next.starts_at)}.` : 'Comunícate con AlertPrompt.'}
                        />
                    ) : (
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                            <Card className="lg:col-span-5">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        Plan {current.plan_name}
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
                                        <p className="text-xs text-muted-foreground">Se renueva automáticamente con las mismas condiciones.</p>
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
                                                Desde el {formatDate(next.starts_at)} pasas al plan {next.plan_name}.
                                            </AlertDescription>
                                        </Alert>
                                    )}
                                </CardContent>
                            </Card>

                            <Card className="lg:col-span-7">
                                <CardHeader>
                                    <CardTitle>Consumo de este mes</CardTitle>
                                    <CardDescription>
                                        Mensajes enviados.{' '}
                                        {data.quotas_enforced
                                            ? 'Al llegar a la cuota no se pueden iniciar más campañas del canal.'
                                            : 'Las cuotas son de referencia.'}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <UsageMeters usage={usage} />
                                </CardContent>
                            </Card>
                        </div>
                    )}
                </>
            )}

            {tab === 'plans' && (
                <section>
                    <p className="mb-4 text-sm text-muted-foreground">
                        Mensajes incluidos por mes en cada canal.
                        {canRequest && ' Elige un plan para solicitar el cambio; AlertPrompt lo aprueba.'}
                    </p>
                    <PlanCards
                        plans={plans}
                        currentKey={data.current_plan}
                        footer={(plan) => {
                            if (plan.key === data.current_plan) return null;
                            if (pending?.requested_plan === plan.key) {
                                return (
                                    <Badge variant="warning" className="mt-auto">
                                        <Clock />
                                        Solicitado
                                    </Badge>
                                );
                            }
                            if (!canRequest || !plan.is_active) return null;

                            return (
                                <Button variant="outline" className="mt-auto" disabled={pending !== null} onClick={() => setRequesting(plan)}>
                                    Solicitar este plan
                                </Button>
                            );
                        }}
                    />
                </section>
            )}

            {tab === 'history' &&
                (history.length === 0 ? (
                    <EmptyState icon={History} title="Sin historial" description="Aquí verás las membresías que has tenido con AlertPrompt." />
                ) : (
                    <Card>
                        <CardContent className="divide-y p-0">
                            {history.map((membership) => (
                                <div key={membership.id} className="flex flex-wrap items-center justify-between gap-3 px-6 py-3 text-sm">
                                    <span>Plan {membership.plan_name}</span>
                                    <span className="text-muted-foreground">
                                        {formatDate(membership.starts_at)} – {formatDate(membership.ends_at)}
                                    </span>
                                    <MembershipStatusBadge status={membership.status} />
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                ))}

            {requesting && (
                <RequestDialog
                    plan={requesting}
                    currentName={plans.find((plan) => plan.key === data.current_plan)?.name ?? null}
                    onClose={() => setRequesting(null)}
                />
            )}
        </div>
    );
}
