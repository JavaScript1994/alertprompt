import { Check, CheckCircle2, Clock, Heart, Puzzle, Send, XCircle } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import { useSearchParams } from 'react-router-dom';
import EmptyState from '@/components/shared/EmptyState';
import { formatDate } from '@/components/shared/MembershipBits';
import PageHeader from '@/components/shared/PageHeader';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { useAuthUser } from '@/hooks/useAuth';
import { useCan } from '@/hooks/usePermissions';
import { apiErrorMessage } from '@/lib/format';
import { cn } from '@/lib/utils';
import { useAddOnInterest, useCancelAddOnRequest, useMyAddOns, useRequestAddOn } from './api';
import { ADD_ON_ICONS, addOnPrice } from './meta';
import type { ClientAddOn } from './types';

function RequestDialog({ addOn, onClose }: { addOn: ClientAddOn; onClose: () => void }) {
    const request = useRequestAddOn();
    const [comment, setComment] = useState('');

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Solicitar {addOn.name}</DialogTitle>
                    <DialogDescription>
                        {addOnPrice(addOn.monthly_price)} / mes + IGV. AlertPrompt revisará tu solicitud; cuando la apruebe, el servicio se
                        activa de inmediato y se suma a la factura de tu plan desde el siguiente periodo. Si tu membresía es anual,
                        recibirás además un comprobante por los meses que faltan del año.
                    </DialogDescription>
                </DialogHeader>
                {request.isError && (
                    <Alert variant="error">
                        <XCircle />
                        <AlertTitle>{apiErrorMessage(request.error, ['add_on', 'comment'], 'No se pudo enviar la solicitud.')}</AlertTitle>
                    </Alert>
                )}
                <Field label="Comentario (opcional)" htmlFor="add-on-comment">
                    <Textarea id="add-on-comment" rows={3} maxLength={500} value={comment} onChange={(event) => setComment(event.target.value)} />
                </Field>
                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button loading={request.isPending} onClick={() => request.mutate({ id: addOn.id, comment: comment.trim() || null }, { onSuccess: onClose })}>
                        <Send />
                        Enviar solicitud
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function AddOnCard({ addOn, badge, children }: { addOn: ClientAddOn; badge?: ReactNode; children?: ReactNode }) {
    const Icon = ADD_ON_ICONS[addOn.key] ?? Puzzle;
    // ?service=<clave>: llega desde una promoción y resalta esa tarjeta.
    const [params] = useSearchParams();
    const highlighted = params.get('service') === addOn.key;

    return (
        <Card
            id={`add-on-${addOn.key}`}
            className={cn('scroll-mt-24 gap-4 p-6', addOn.state === 'coming_soon' && 'border-dashed', highlighted && 'ring-2 ring-primary dark:ring-brand-300')}
        >
            <div className="flex items-start justify-between gap-3">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-lightprimary text-primary dark:text-brand-200">
                    <Icon className="size-5" />
                </span>
                {badge}
            </div>
            <div>
                <p className="font-semibold text-foreground">{addOn.name}</p>
                {addOn.description && <p className="mt-1 text-sm text-muted-foreground">{addOn.description}</p>}
            </div>
            {children && <div className="mt-auto space-y-3">{children}</div>}
        </Card>
    );
}

function Section({ title, description, children }: { title: string; description: string; children: ReactNode }) {
    return (
        <section className="mb-8">
            <h2 className="text-base font-semibold text-foreground">{title}</h2>
            <p className="mb-4 text-sm text-muted-foreground">{description}</p>
            <div className="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">{children}</div>
        </section>
    );
}

function Price({ value }: { value: string | null }) {
    return (
        <p>
            <span className="text-2xl font-semibold tracking-tight text-foreground tabular-nums">{addOnPrice(value)}</span>
            <span className="ml-1 text-sm text-muted-foreground">/ mes + IGV</span>
        </p>
    );
}

export default function AddOnsPage() {
    const { data, isLoading } = useMyAddOns();
    const { data: user } = useAuthUser();
    // En modo soporte no se solicita en nombre del cliente (el backend lo bloquea).
    const canRequest = useCan()('add_ons.request') && !user?.impersonating;
    const cancelRequest = useCancelAddOnRequest();
    const interest = useAddOnInterest();
    const [requesting, setRequesting] = useState<ClientAddOn | null>(null);
    const service = useSearchParams()[0].get('service');

    // Llega desde una promoción: lleva la vista a la tarjeta cuando ya se pintó.
    useEffect(() => {
        if (service && data) document.getElementById(`add-on-${service}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, [service, data]);

    if (isLoading || !data) return <Skeleton className="h-96 w-full rounded-xl" />;

    const active = data.filter((addOn) => addOn.state === 'contracted' || addOn.state === 'included');
    const available = data.filter((addOn) => addOn.state === 'available' || addOn.state === 'pending');
    const upcoming = data.filter((addOn) => addOn.state === 'coming_soon');

    return (
        <div>
            <PageHeader title="Servicios" description="Amplía lo que puedes hacer con AlertPrompt. Los servicios contratados se suman a tu membresía." />

            {data.length === 0 && <EmptyState icon={Puzzle} title="Sin servicios por ahora" description="Aquí verás los servicios adicionales de AlertPrompt." />}

            {active.length > 0 && (
                <Section title="Activos" description="Servicios que ya puedes usar.">
                    {active.map((addOn) => (
                        <AddOnCard
                            key={addOn.id}
                            addOn={addOn}
                            badge={
                                addOn.state === 'contracted' ? (
                                    <Badge variant="success">
                                        <Check />
                                        Contratado
                                    </Badge>
                                ) : (
                                    <Badge variant="primary">Incluido en tu plan</Badge>
                                )
                            }
                        >
                            {addOn.contract && (
                                <p className="text-sm text-muted-foreground">
                                    {addOnPrice(addOn.contract.monthly_price)} / mes + IGV, desde el {formatDate(addOn.contract.starts_at)}.
                                </p>
                            )}
                        </AddOnCard>
                    ))}
                </Section>
            )}

            {available.length > 0 && (
                <Section title="Disponibles" description={canRequest ? 'Solicítalos y AlertPrompt los activa.' : 'Pide a un administrador de tu cuenta que los solicite.'}>
                    {available.map((addOn) => {
                        const pending = addOn.pending_request;
                        const rejected = !pending && addOn.last_decision?.status === 'rejected' ? addOn.last_decision : null;

                        return (
                            <AddOnCard
                                key={addOn.id}
                                addOn={addOn}
                                badge={
                                    pending && (
                                        <Badge variant="warning">
                                            <Clock />
                                            En revisión
                                        </Badge>
                                    )
                                }
                            >
                                <Price value={addOn.monthly_price} />
                                {pending && (
                                    <p className="text-xs text-muted-foreground">
                                        Solicitado el {formatDate(pending.created_at.slice(0, 10))}
                                        {pending.requested_by && ` por ${pending.requested_by}`}. Te avisaremos cuando AlertPrompt lo revise.
                                    </p>
                                )}
                                {rejected && (
                                    <p className="text-xs text-error">
                                        Tu última solicitud no fue aprobada{rejected.decision_note ? `: ${rejected.decision_note}` : '.'}
                                    </p>
                                )}
                                {canRequest &&
                                    (pending ? (
                                        <Button
                                            variant="outline"
                                            className="w-full"
                                            loading={cancelRequest.isPending && cancelRequest.variables === addOn.id}
                                            onClick={() => cancelRequest.mutate(addOn.id)}
                                        >
                                            Cancelar solicitud
                                        </Button>
                                    ) : (
                                        <Button variant="lightprimary" className="w-full" onClick={() => setRequesting(addOn)}>
                                            Solicitar
                                        </Button>
                                    ))}
                            </AddOnCard>
                        );
                    })}
                </Section>
            )}

            {upcoming.length > 0 && (
                <Section title="Próximamente" description="Estamos trabajando en ellos. Marca los que te interesan y te avisaremos cuando estén listos.">
                    {upcoming.map((addOn) => (
                        <AddOnCard key={addOn.id} addOn={addOn} badge={<Badge variant="info">Próximamente</Badge>}>
                            {addOn.interested && (
                                <p className="inline-flex items-center gap-1.5 text-sm text-success">
                                    <CheckCircle2 className="size-4" />
                                    Te avisaremos cuando esté disponible.
                                </p>
                            )}
                            {canRequest && (
                                <Button
                                    variant={addOn.interested ? 'ghost' : 'outlineprimary'}
                                    className="w-full"
                                    loading={interest.isPending && interest.variables?.id === addOn.id}
                                    onClick={() => interest.mutate({ id: addOn.id, interested: !addOn.interested })}
                                >
                                    {addOn.interested ? (
                                        'Ya no me interesa'
                                    ) : (
                                        <>
                                            <Heart />
                                            Me interesa
                                        </>
                                    )}
                                </Button>
                            )}
                        </AddOnCard>
                    ))}
                </Section>
            )}

            {requesting && <RequestDialog addOn={requesting} onClose={() => setRequesting(null)} />}
        </div>
    );
}
