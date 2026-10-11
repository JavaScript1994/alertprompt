import { Check, Inbox, X, XCircle } from 'lucide-react';
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
import { useCan } from '@/hooks/usePermissions';
import { apiErrorMessage, formatDateTime } from '@/lib/format';
import { useAddOnRequests, useDecideAddOnRequest } from './adminApi';
import { addOnPrice } from './meta';
import type { AddOnRequest, AddOnRequestStatus } from './types';

function DecisionDialog({ request, approve, onClose }: { request: AddOnRequest; approve: boolean; onClose: () => void }) {
    const decide = useDecideAddOnRequest();
    const [note, setNote] = useState('');

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>{approve ? 'Aprobar servicio' : 'Rechazar solicitud'}</DialogTitle>
                    <DialogDescription>
                        {request.tenant.name}: {request.add_on.name}
                        {approve &&
                            ` (${addOnPrice(request.add_on.monthly_price)} / mes + IGV). Se activa hoy y se suma a su factura desde el siguiente periodo.`}
                    </DialogDescription>
                </DialogHeader>
                {decide.isError && (
                    <Alert variant="error">
                        <XCircle />
                        <AlertTitle>{apiErrorMessage(decide.error, ['request', 'add_on', 'note'], 'No se pudo guardar la decisión.')}</AlertTitle>
                    </Alert>
                )}
                <Field label={approve ? 'Nota (opcional)' : 'Motivo (lo verá el cliente)'} htmlFor="add-on-decision-note">
                    <Textarea id="add-on-decision-note" rows={3} maxLength={500} value={note} onChange={(event) => setNote(event.target.value)} />
                </Field>
                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button
                        variant={approve ? 'default' : 'destructive'}
                        loading={decide.isPending}
                        disabled={!approve && note.trim() === ''}
                        onClick={() => decide.mutate({ id: request.id, approve, note: note.trim() || undefined }, { onSuccess: onClose })}
                    >
                        {approve ? 'Aprobar' : 'Rechazar'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

const STATUS_TEXT: Record<AddOnRequestStatus, string> = {
    pending: 'Pendiente',
    approved: 'Aprobada',
    rejected: 'Rechazada',
    cancelled: 'Cancelada por el cliente',
};

export default function AdminAddOnRequestsPage() {
    const [status, setStatus] = useState<AddOnRequestStatus>('pending');
    const [page, setPage] = useState(1);
    const { data, isLoading } = useAddOnRequests(status, page);
    const canManage = useCan()('admin.memberships.manage');
    const [deciding, setDeciding] = useState<{ request: AddOnRequest; approve: boolean } | null>(null);

    return (
        <div>
            <PageHeader title="Solicitudes de servicios" description="Servicios adicionales que piden los clientes desde Configuración → Servicios." />

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
                        {data.data.map((request) => (
                            <Card key={request.id} className="gap-2 p-5">
                                <div className="flex flex-wrap items-start justify-between gap-4">
                                    <div className="min-w-0 space-y-1">
                                        <Link to={`/admin/clients/${request.tenant.id}`} className="font-semibold text-foreground hover:text-primary">
                                            {request.tenant.name}
                                        </Link>
                                        <p className="text-sm">
                                            <span className="font-medium">{request.add_on.name}</span>
                                            <span className="text-muted-foreground"> · {addOnPrice(request.add_on.monthly_price)} / mes + IGV</span>
                                        </p>
                                        {request.comment && <p className="text-sm text-muted-foreground">«{request.comment}»</p>}
                                        <p className="text-xs text-muted-foreground">
                                            {STATUS_TEXT[request.status]} · pedida por {request.requested_by ?? '—'} el {formatDateTime(request.created_at, 'short')}
                                            {request.decided_by && ` · decidida por ${request.decided_by}`}
                                        </p>
                                        {request.decision_note && <p className="text-xs text-muted-foreground">Nota: {request.decision_note}</p>}
                                    </div>
                                    {canManage && request.status === 'pending' && (
                                        <div className="flex gap-2">
                                            <Button size="sm" onClick={() => setDeciding({ request, approve: true })}>
                                                <Check />
                                                Aprobar
                                            </Button>
                                            <Button size="sm" variant="outline" className="text-error hover:text-error" onClick={() => setDeciding({ request, approve: false })}>
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

            {deciding && <DecisionDialog request={deciding.request} approve={deciding.approve} onClose={() => setDeciding(null)} />}
        </div>
    );
}
