import { Plus, Puzzle, XCircle } from 'lucide-react';
import { useState } from 'react';
import ConfirmDialog from '@/components/shared/ConfirmDialog';
import EmptyState from '@/components/shared/EmptyState';
import { formatDate } from '@/components/shared/MembershipBits';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { useClientModules } from '@/hooks/useModulesAdmin';
import { useCan } from '@/hooks/usePermissions';
import { apiErrorMessage } from '@/lib/format';
import { useAddOnCatalog, useCancelContract, useClientAddOns, useContractAddOn } from './adminApi';
import { addOnPrice } from './meta';
import type { TenantAddOn } from './types';

/** Alta directa (p. ej. contrato firmado fuera del panel), sin solicitud del cliente. */
function ContractDialog({ clientId, onClose }: { clientId: number; onClose: () => void }) {
    const { data: catalog } = useAddOnCatalog();
    const { data: enabled } = useClientModules(clientId);
    const contract = useContractAddOn(clientId);
    const [addOnId, setAddOnId] = useState('');
    const options = (catalog ?? []).filter(
        (addOn) => addOn.is_active && addOn.status === 'available' && addOn.module && !enabled?.includes(addOn.module),
    );

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Contratar servicio</DialogTitle>
                    <DialogDescription>Se activa hoy con el precio vigente del catálogo y se suma a su factura desde el siguiente periodo. Si su membresía es anual, se emite un comprobante por los meses que faltan del año.</DialogDescription>
                </DialogHeader>
                {contract.isError && (
                    <Alert variant="error">
                        <XCircle />
                        <AlertTitle>{apiErrorMessage(contract.error, ['add_on', 'add_on_id'], 'No se pudo contratar el servicio.')}</AlertTitle>
                    </Alert>
                )}
                {options.length === 0 ? (
                    <p className="text-sm text-muted-foreground">El cliente ya tiene activos todos los servicios disponibles.</p>
                ) : (
                    <Field label="Servicio" htmlFor="contract-add-on">
                        <Select value={addOnId} onValueChange={(v) => v && setAddOnId(v)}>
                            <SelectTrigger id="contract-add-on" className="w-full">
                                <SelectValue placeholder="Elige un servicio" />
                            </SelectTrigger>
                            <SelectContent>
                                {options.map((addOn) => (
                                    <SelectItem key={addOn.id} value={String(addOn.id)}>
                                        {addOn.name} · {addOnPrice(addOn.monthly_price)} / mes
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                )}
                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button loading={contract.isPending} disabled={addOnId === ''} onClick={() => contract.mutate(Number(addOnId), { onSuccess: onClose })}>
                        Contratar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function ContractRow({ contract, onCancel }: { contract: TenantAddOn; onCancel?: () => void }) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3 px-6 py-3 text-sm">
            <div className="min-w-0">
                <p className="font-medium text-foreground">{contract.add_on.name}</p>
                <p className="text-xs text-muted-foreground">
                    {addOnPrice(contract.monthly_price)} / mes + IGV · desde el {formatDate(contract.starts_at)}
                    {contract.activated_by && ` · activó ${contract.activated_by}`}
                </p>
                {contract.status === 'cancelled' && (
                    <p className="text-xs text-muted-foreground">
                        Cancelado el {formatDate(contract.cancelled_at?.slice(0, 10) ?? '')}
                        {contract.cancelled_by && ` por ${contract.cancelled_by}`}
                        {contract.cancel_reason && `: ${contract.cancel_reason}`}
                    </p>
                )}
            </div>
            {contract.status === 'active' ? (
                onCancel && (
                    <Button variant="outline" size="sm" className="text-error hover:text-error" onClick={onCancel}>
                        Cancelar servicio
                    </Button>
                )
            ) : (
                <Badge variant="neutral">Cancelado</Badge>
            )}
        </div>
    );
}

export default function ClientAddOnsTab({ clientId }: { clientId: number }) {
    const { data, isLoading } = useClientAddOns(clientId);
    const cancel = useCancelContract(clientId);
    const canManage = useCan()('admin.memberships.manage');
    const [isContracting, setIsContracting] = useState(false);
    const [cancelling, setCancelling] = useState<TenantAddOn | null>(null);
    const [reason, setReason] = useState('');

    if (isLoading || !data) return <Skeleton className="h-64 w-full rounded-xl" />;

    const active = data.filter((contract) => contract.status === 'active');
    const history = data.filter((contract) => contract.status === 'cancelled');
    const closeCancel = () => {
        setCancelling(null);
        setReason('');
        cancel.reset();
    };

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader className="flex flex-row items-center justify-between gap-3">
                    <CardTitle>Servicios contratados</CardTitle>
                    {canManage && (
                        <Button size="sm" onClick={() => setIsContracting(true)}>
                            <Plus />
                            Contratar servicio
                        </Button>
                    )}
                </CardHeader>
                <CardContent className="divide-y p-0">
                    {active.length === 0 ? (
                        <EmptyState icon={Puzzle} title="Sin servicios contratados" description="Los que apruebes o contrates aparecerán aquí." />
                    ) : (
                        active.map((contract) => (
                            <ContractRow key={contract.id} contract={contract} onCancel={canManage ? () => setCancelling(contract) : undefined} />
                        ))
                    )}
                </CardContent>
            </Card>

            {history.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>Historial</CardTitle>
                    </CardHeader>
                    <CardContent className="divide-y p-0">
                        {history.map((contract) => (
                            <ContractRow key={contract.id} contract={contract} />
                        ))}
                    </CardContent>
                </Card>
            )}

            {isContracting && <ContractDialog clientId={clientId} onClose={() => setIsContracting(false)} />}

            <ConfirmDialog
                open={cancelling !== null}
                title={`Cancelar ${cancelling?.add_on.name ?? 'servicio'}`}
                description="El módulo se desactiva de inmediato (salvo que su plan lo incluya) y deja de cobrarse. Si su membresía es anual, se emite una nota de crédito por los meses ya cobrados que no usará (queda como saldo a favor)."
                confirmLabel="Cancelar servicio"
                destructive
                loading={cancel.isPending}
                onConfirm={() => cancelling && reason.trim() !== '' && cancel.mutate({ id: cancelling.id, reason: reason.trim() }, { onSuccess: closeCancel })}
                onCancel={closeCancel}
            >
                {cancel.isError && (
                    <Alert variant="error">
                        <XCircle />
                        <AlertTitle>{apiErrorMessage(cancel.error, ['reason', 'add_on'], 'No se pudo cancelar el servicio.')}</AlertTitle>
                    </Alert>
                )}
                <Field label="Motivo" htmlFor="cancel-add-on-reason">
                    <Textarea id="cancel-add-on-reason" rows={2} maxLength={500} value={reason} onChange={(event) => setReason(event.target.value)} />
                </Field>
            </ConfirmDialog>
        </div>
    );
}
