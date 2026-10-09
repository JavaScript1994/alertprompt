import { Ban, Banknote, Printer, XCircle } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { money } from '@/components/shared/InvoiceBits';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useRecordPayment, useVoidInvoice } from '@/hooks/useBilling';
import { apiErrorMessage, isoDate } from '@/lib/format';
import type { Invoice } from '@/types';

function PaymentDialog({ invoice, onClose }: { invoice: Invoice; onClose: () => void }) {
    const record = useRecordPayment();
    const [amount, setAmount] = useState(invoice.balance);
    const [method, setMethod] = useState('transfer');
    const [paidAt, setPaidAt] = useState(() => isoDate(new Date()));
    const [reference, setReference] = useState('');

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();
        record.mutate({ invoiceId: invoice.id, amount, method, paid_at: paidAt, reference: reference || undefined }, { onSuccess: onClose });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Registrar pago</DialogTitle>
                    <DialogDescription>
                        {invoice.code} · saldo {money(invoice.balance)}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={onSubmit} className="space-y-4">
                    {record.isError && (
                        <Alert variant="error">
                            <XCircle />
                            <AlertTitle>{apiErrorMessage(record.error, ['amount', 'method', 'paid_at', 'invoice'], 'No se pudo registrar el pago.')}</AlertTitle>
                        </Alert>
                    )}
                    <div className="grid grid-cols-2 gap-4">
                        <Field label="Monto (S/)" htmlFor="p-amount">
                            <Input id="p-amount" type="number" min="0.01" step="0.01" value={amount} onChange={(e) => setAmount(e.target.value)} />
                        </Field>
                        <Field label="Medio" htmlFor="p-method">
                            <Select value={method} onValueChange={(v) => v && setMethod(v)}>
                                <SelectTrigger id="p-method" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="transfer">Transferencia</SelectItem>
                                    <SelectItem value="yape">Yape</SelectItem>
                                    <SelectItem value="plin">Plin</SelectItem>
                                    <SelectItem value="cash">Efectivo</SelectItem>
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field label="Fecha" htmlFor="p-date">
                            <Input id="p-date" type="date" value={paidAt} onChange={(e) => setPaidAt(e.target.value)} />
                        </Field>
                        <Field label="N° de operación" htmlFor="p-ref">
                            <Input id="p-ref" value={reference} onChange={(e) => setReference(e.target.value)} />
                        </Field>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            Cancelar
                        </Button>
                        <Button type="submit" loading={record.isPending}>
                            Registrar pago
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function VoidDialog({ invoice, onClose }: { invoice: Invoice; onClose: () => void }) {
    const voidInvoice = useVoidInvoice();
    const [reason, setReason] = useState('');

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Anular {invoice.code}</DialogTitle>
                    <DialogDescription>Solo se anulan comprobantes sin pagos. El número no se reutiliza.</DialogDescription>
                </DialogHeader>
                {voidInvoice.isError && (
                    <Alert variant="error">
                        <AlertTitle>{apiErrorMessage(voidInvoice.error, ['invoice', 'reason'], 'No se pudo anular.')}</AlertTitle>
                    </Alert>
                )}
                <Field label="Motivo" htmlFor="v-reason">
                    <Input id="v-reason" value={reason} onChange={(e) => setReason(e.target.value)} />
                </Field>
                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button
                        variant="destructive"
                        disabled={reason.trim() === ''}
                        loading={voidInvoice.isPending}
                        onClick={() => voidInvoice.mutate({ invoiceId: invoice.id, reason }, { onSuccess: onClose })}
                    >
                        Anular
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/** Acciones de una fila de comprobante en la plataforma. */
export default function InvoiceActions({ invoice, canManage }: { invoice: Invoice; canManage: boolean }) {
    const [dialog, setDialog] = useState<'pay' | 'void' | null>(null);
    const open = invoice.status === 'issued';

    return (
        <div className="inline-flex gap-1">
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button variant="ghost" size="icon-sm" asChild>
                        <Link to={`/admin/invoices/${invoice.id}`} aria-label={`Ver ${invoice.code}`}>
                            <Printer />
                        </Link>
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Ver e imprimir</TooltipContent>
            </Tooltip>
            {canManage && open && (
                <>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button variant="ghostsuccess" size="icon-sm" aria-label="Registrar pago" onClick={() => setDialog('pay')}>
                                <Banknote />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Registrar pago</TooltipContent>
                    </Tooltip>
                    {Number(invoice.paid) === 0 && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button variant="ghosterror" size="icon-sm" aria-label="Anular" onClick={() => setDialog('void')}>
                                    <Ban />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>Anular</TooltipContent>
                        </Tooltip>
                    )}
                </>
            )}
            {dialog === 'pay' && <PaymentDialog invoice={invoice} onClose={() => setDialog(null)} />}
            {dialog === 'void' && <VoidDialog invoice={invoice} onClose={() => setDialog(null)} />}
        </div>
    );
}
