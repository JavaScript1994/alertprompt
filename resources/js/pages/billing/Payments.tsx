import { createColumnHelper } from '@tanstack/react-table';
import { FileText, Landmark, Printer } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import DataTable from '@/components/shared/DataTable';
import EmptyState from '@/components/shared/EmptyState';
import { InvoiceStatusBadge, money, shortDate } from '@/components/shared/InvoiceBits';
import PageHeader from '@/components/shared/PageHeader';
import Pagination from '@/components/shared/Pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useBillingSummary, useMyInvoices, useMyPayments } from '@/hooks/useBilling';
import type { Invoice, PaymentRecord } from '@/types';

const invoiceColumn = createColumnHelper<Invoice>();
const paymentColumn = createColumnHelper<PaymentRecord>();

export default function Payments() {
    const [invoicePage, setInvoicePage] = useState(1);
    const [paymentPage, setPaymentPage] = useState(1);
    const { data: summary } = useBillingSummary();
    const { data: invoices, isLoading } = useMyInvoices(invoicePage);
    const { data: payments } = useMyPayments(paymentPage);

    const invoiceColumns = useMemo(
        () => [
            invoiceColumn.accessor('code', { header: 'Comprobante', cell: (info) => <span className="font-mono text-xs">{info.getValue()}</span> }),
            invoiceColumn.accessor('description', { header: 'Concepto', cell: (info) => <span className="block max-w-[260px] truncate">{info.getValue()}</span> }),
            invoiceColumn.accessor('issue_date', { header: 'Emisión', cell: (info) => shortDate(info.getValue()) }),
            invoiceColumn.accessor('due_date', { header: 'Vence', cell: (info) => shortDate(info.getValue()) }),
            invoiceColumn.accessor('total', { header: 'Total', meta: { className: 'text-right tabular-nums' }, cell: (info) => money(info.getValue()) }),
            invoiceColumn.display({ id: 'status', header: 'Estado', cell: (info) => <InvoiceStatusBadge invoice={info.row.original} /> }),
            invoiceColumn.display({
                id: 'print',
                header: '',
                meta: { className: 'w-[1%]' },
                cell: (info) => (
                    <Button variant="ghost" size="icon-sm" asChild>
                        <Link to={`/billing/invoices/${info.row.original.id}`} aria-label={`Ver ${info.row.original.code}`}>
                            <Printer />
                        </Link>
                    </Button>
                ),
            }),
        ],
        [],
    );

    const paymentColumns = useMemo(
        () => [
            paymentColumn.accessor('paid_at', { header: 'Fecha', cell: (info) => shortDate(info.getValue()) }),
            paymentColumn.accessor('invoice_code', { header: 'Comprobante', cell: (info) => <span className="font-mono text-xs">{info.getValue()}</span> }),
            paymentColumn.accessor('method_label', { header: 'Medio' }),
            paymentColumn.accessor('reference', { header: 'Operación', cell: (info) => info.getValue() ?? '—' }),
            paymentColumn.accessor('amount', { header: 'Monto', meta: { className: 'text-right tabular-nums' }, cell: (info) => money(info.getValue()) }),
        ],
        [],
    );

    return (
        <div>
            <PageHeader title="Pagos" description="Tus comprobantes de AlertPrompt y los pagos registrados." />

            <div className="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                <Card className="gap-1 p-5">
                    <p className="text-xs text-muted-foreground">Saldo pendiente</p>
                    <p className="text-2xl font-semibold tabular-nums">{summary ? money(summary.balance) : '—'}</p>
                    {summary && summary.overdue_count > 0 && (
                        <p className="text-xs font-medium text-error">
                            {summary.overdue_count} comprobante{summary.overdue_count === 1 ? '' : 's'} vencido{summary.overdue_count === 1 ? '' : 's'}
                        </p>
                    )}
                </Card>
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Landmark className="size-4" />
                            Cómo pagar
                        </CardTitle>
                        <CardDescription>{summary?.transfer_instructions}</CardDescription>
                    </CardHeader>
                </Card>
            </div>

            <h2 className="mb-3 text-lg">Comprobantes</h2>
            {isLoading ? (
                <Skeleton className="h-48 w-full rounded-xl" />
            ) : invoices && invoices.data.length > 0 ? (
                <>
                    <DataTable data={invoices.data} columns={invoiceColumns} minWidth="min-w-[760px]" />
                    <Pagination meta={invoices.meta} noun="comprobantes" onPageChange={setInvoicePage} />
                </>
            ) : (
                <EmptyState icon={FileText} title="Aún no tienes comprobantes" />
            )}

            <h2 className="mt-8 mb-3 text-lg">Pagos registrados</h2>
            {payments && payments.data.length > 0 ? (
                <>
                    <DataTable data={payments.data} columns={paymentColumns} minWidth="min-w-[600px]" />
                    <Pagination meta={payments.meta} noun="pagos" onPageChange={setPaymentPage} />
                </>
            ) : (
                <Card>
                    <CardContent className="py-2 text-sm text-muted-foreground">Sin pagos registrados todavía.</CardContent>
                </Card>
            )}
        </div>
    );
}
