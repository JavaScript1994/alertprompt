import { FileText, Plus, XCircle } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import EmptyState from '@/components/shared/EmptyState';
import Pagination from '@/components/shared/Pagination';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { useAdminInvoices, useIssueInvoice } from '@/hooks/useBilling';
import { useCan } from '@/hooks/usePermissions';
import { apiErrorMessage } from '@/lib/format';
import InvoicesTable from '../billing/InvoicesTable';

function IssueDialog({ clientId, onClose }: { clientId: number; onClose: () => void }) {
    const issue = useIssueInvoice(clientId);
    const [description, setDescription] = useState('');
    const [subtotal, setSubtotal] = useState('');
    const igv = Number(subtotal || 0) * 0.18;

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();
        issue.mutate({ description, subtotal }, { onSuccess: onClose });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Emitir comprobante</DialogTitle>
                    <DialogDescription>Factura si el cliente tiene RUC; boleta si no. Se suma IGV (18%).</DialogDescription>
                </DialogHeader>
                <form onSubmit={onSubmit} className="space-y-4">
                    {issue.isError && (
                        <Alert variant="error">
                            <XCircle />
                            <AlertTitle>{apiErrorMessage(issue.error, ['description', 'subtotal'], 'No se pudo emitir.')}</AlertTitle>
                        </Alert>
                    )}
                    <Field label="Concepto" htmlFor="i-desc">
                        <Input id="i-desc" placeholder="Implementación y capacitación" value={description} onChange={(e) => setDescription(e.target.value)} />
                    </Field>
                    <Field
                        label="Monto sin IGV (S/)"
                        htmlFor="i-sub"
                        hint={subtotal ? `IGV S/ ${igv.toFixed(2)} · total S/ ${(Number(subtotal) + igv).toFixed(2)}` : undefined}
                    >
                        <Input id="i-sub" type="number" min="0.01" step="0.01" value={subtotal} onChange={(e) => setSubtotal(e.target.value)} />
                    </Field>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            Cancelar
                        </Button>
                        <Button type="submit" loading={issue.isPending} disabled={!description.trim() || !subtotal}>
                            Emitir
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function ClientBillingTab({ clientId }: { clientId: number }) {
    const [page, setPage] = useState(1);
    const [isIssuing, setIsIssuing] = useState(false);
    const { data, isLoading } = useAdminInvoices({ page, client_id: clientId });
    const canManage = useCan()('admin.billing.manage');

    return (
        <div className="space-y-4">
            {canManage && (
                <div className="flex justify-end">
                    <Button onClick={() => setIsIssuing(true)}>
                        <Plus />
                        Emitir comprobante
                    </Button>
                </div>
            )}
            {isLoading ? (
                <Skeleton className="h-48 w-full rounded-xl" />
            ) : data && data.data.length > 0 ? (
                <>
                    <InvoicesTable invoices={data.data} showClient={false} />
                    <Pagination meta={data.meta} noun="comprobantes" onPageChange={setPage} />
                </>
            ) : (
                <EmptyState icon={FileText} title="Sin comprobantes" description="Se emiten solos al activar o renovar la membresía." />
            )}
            {isIssuing && <IssueDialog clientId={clientId} onClose={() => setIsIssuing(false)} />}
        </div>
    );
}
