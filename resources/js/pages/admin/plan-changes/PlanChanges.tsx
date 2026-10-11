import { ArrowRight, Check, Inbox, X, XCircle } from 'lucide-react';
import { useState } from 'react';
import { Link } from 'react-router-dom';
import EmptyState from '@/components/shared/EmptyState';
import PageHeader from '@/components/shared/PageHeader';
import Pagination from '@/components/shared/Pagination';
import Tabs from '@/components/shared/Tabs';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { useDecidePlanChange, usePlanChanges } from '@/hooks/useMemberships';
import { useCan } from '@/hooks/usePermissions';
import { apiErrorMessage, formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PlanChangeRequest, PlanChangeStatus } from '@/types';

function DecisionDialog({ change, approve, onClose }: { change: PlanChangeRequest; approve: boolean; onClose: () => void }) {
    const decide = useDecidePlanChange();
    const [when, setWhen] = useState<'now' | 'next_period'>('now');
    const [note, setNote] = useState('');

    const options = [
        { value: 'now' as const, title: 'Desde hoy', text: 'Reemplaza la membresía vigente hoy y el cliente pasa a los módulos del nuevo plan.' },
        {
            value: 'next_period' as const,
            title: 'Desde el próximo período',
            text: 'La membresía actual termina al cierre de su ciclo de facturación y el nuevo plan empieza al día siguiente.',
        },
    ];

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>{approve ? 'Aprobar cambio de plan' : 'Rechazar solicitud'}</DialogTitle>
                    <DialogDescription>
                        {change.tenant?.name}: {change.current_plan_name ?? '—'} → {change.requested_plan_name}
                    </DialogDescription>
                </DialogHeader>
                {decide.isError && (
                    <Alert variant="error">
                        <XCircle />
                        <AlertTitle>{apiErrorMessage(decide.error, ['request', 'note', 'when', 'starts_at'], 'No se pudo guardar la decisión.')}</AlertTitle>
                    </Alert>
                )}
                {approve && (
                    <fieldset className="space-y-2">
                        <legend className="mb-2 text-sm font-medium">¿Desde cuándo?</legend>
                        {options.map((option) => (
                            <label
                                key={option.value}
                                aria-label={option.title}
                                className={cn(
                                    'flex cursor-pointer gap-3 rounded-lg border p-3',
                                    when === option.value && 'border-primary bg-lightprimary dark:border-brand-300',
                                )}
                            >
                                <input
                                    type="radio"
                                    name="when"
                                    value={option.value}
                                    checked={when === option.value}
                                    onChange={() => setWhen(option.value)}
                                    className="mt-1 accent-primary"
                                />
                                <span>
                                    <span className="block text-sm font-medium">{option.title}</span>
                                    <span className="block text-xs text-muted-foreground">{option.text}</span>
                                </span>
                            </label>
                        ))}
                    </fieldset>
                )}
                <Field label={approve ? 'Nota (opcional)' : 'Motivo (lo verá el cliente)'} htmlFor="decision-note">
                    <Textarea id="decision-note" rows={3} value={note} onChange={(event) => setNote(event.target.value)} />
                </Field>
                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button
                        variant={approve ? 'default' : 'destructive'}
                        loading={decide.isPending}
                        disabled={!approve && note.trim() === ''}
                        onClick={() => decide.mutate({ id: change.id, approve, when: approve ? when : undefined, note: note.trim() || undefined }, { onSuccess: onClose })}
                    >
                        {approve ? 'Aprobar' : 'Rechazar'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

const STATUS_TEXT: Record<PlanChangeStatus, string> = {
    pending: 'Pendiente',
    approved: 'Aprobada',
    rejected: 'Rechazada',
    cancelled: 'Cancelada por el cliente',
};

export default function PlanChanges() {
    const [status, setStatus] = useState<PlanChangeStatus>('pending');
    const [page, setPage] = useState(1);
    const { data, isLoading } = usePlanChanges(status, page);
    const canManage = useCan()('admin.memberships.manage');
    const [deciding, setDeciding] = useState<{ change: PlanChangeRequest; approve: boolean } | null>(null);

    return (
        <div>
            <PageHeader title="Solicitudes de plan" description="Cambios de plan que piden los clientes desde su pantalla de Membresía." />

            <Tabs
                tabs={[
                    { key: 'pending', label: 'Pendientes' },
                    { key: 'approved', label: 'Aprobadas' },
                    { key: 'rejected', label: 'Rechazadas' },
                    { key: 'cancelled', label: 'Canceladas' },
                ]}
                active={status}
                onChange={(key) => {
                    setStatus(key);
                    setPage(1);
                }}
            />

            {isLoading ? (
                <Skeleton className="h-48 w-full rounded-xl" />
            ) : !data || data.data.length === 0 ? (
                <EmptyState icon={Inbox} title={status === 'pending' ? 'No hay solicitudes pendientes' : 'Sin solicitudes en esta vista'} />
            ) : (
                <>
                    <div className="space-y-3">
                        {data.data.map((change) => (
                            <Card key={change.id} className="gap-2 p-5">
                                <div className="flex flex-wrap items-start justify-between gap-4">
                                    <div className="min-w-0 space-y-1">
                                        {change.tenant && (
                                            <Link to={`/admin/clients/${change.tenant.id}`} className="font-semibold text-foreground hover:text-primary">
                                                {change.tenant.name}
                                            </Link>
                                        )}
                                        <p className="flex items-center gap-2 text-sm">
                                            {change.current_plan_name ?? '—'}
                                            <ArrowRight className="size-4 text-muted-foreground" />
                                            <span className="font-medium">{change.requested_plan_name}</span>
                                        </p>
                                        {change.comment && <p className="text-sm text-muted-foreground">«{change.comment}»</p>}
                                        <p className="text-xs text-muted-foreground">
                                            {STATUS_TEXT[change.status]} · pedida por {change.requested_by ?? '—'} el {formatDateTime(change.created_at, 'short')}
                                            {change.decided_by && ` · decidida por ${change.decided_by}`}
                                            {change.effective_from && ` · desde el ${change.effective_from.split('-').toReversed().join('/')}`}
                                        </p>
                                        {change.decision_note && <p className="text-xs text-muted-foreground">Nota: {change.decision_note}</p>}
                                    </div>
                                    {canManage && change.status === 'pending' && (
                                        <div className="flex gap-2">
                                            <Button size="sm" onClick={() => setDeciding({ change, approve: true })}>
                                                <Check />
                                                Aprobar
                                            </Button>
                                            <Button size="sm" variant="outline" className="text-error hover:text-error" onClick={() => setDeciding({ change, approve: false })}>
                                                <X />
                                                Rechazar
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            </Card>
                        ))}
                    </div>
                    <Pagination meta={data.meta} noun="solicitudes" onPageChange={setPage} />
                </>
            )}

            {deciding && <DecisionDialog change={deciding.change} approve={deciding.approve} onClose={() => setDeciding(null)} />}
        </div>
    );
}
