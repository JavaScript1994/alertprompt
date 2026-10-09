import { Printer } from 'lucide-react';
import { useParams } from 'react-router-dom';
import Logo from '@/components/shared/Logo';
import { InvoiceStatusBadge, money, shortDate } from '@/components/shared/InvoiceBits';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useInvoice } from '@/hooks/useBilling';

/** Comprobante imprimible. Si SUNAT no lo aceptó, se rotula como proforma. */
export default function InvoicePrint({ scope }: { scope: 'client' | 'admin' }) {
    const id = Number(useParams<{ id: string }>().id);
    const { data: invoice, isLoading } = useInvoice(id, scope);

    if (isLoading || !invoice) return <Skeleton className="h-[600px] w-full rounded-xl" />;

    const title = invoice.is_electronic
        ? `${invoice.document_type === 'factura' ? 'Factura' : 'Boleta de venta'} electrónica`
        : 'Proforma';

    return (
        <div className="mx-auto max-w-3xl">
            <div className="mb-4 flex justify-end print:hidden">
                <Button variant="outline" onClick={() => window.print()}>
                    <Printer />
                    Imprimir o guardar PDF
                </Button>
            </div>

            {!invoice.is_electronic && (
                <Alert variant="info" className="mb-4 print:hidden">
                    <AlertDescription>
                        Documento interno de cobro. Aún no es un comprobante electrónico válido ante SUNAT: se emitirá cuando
                        se conecte la facturación electrónica.
                    </AlertDescription>
                </Alert>
            )}

            <article className="rounded-xl border bg-card p-8 shadow-md print:border-0 print:shadow-none">
                <header className="flex flex-wrap items-start justify-between gap-6 border-b pb-6">
                    <div>
                        <Logo />
                        <p className="mt-3 text-sm text-muted-foreground">AlertPrompt · Lima, Perú</p>
                    </div>
                    <div className="rounded-lg border px-5 py-3 text-center">
                        <p className="text-sm font-semibold tracking-wide uppercase">{title}</p>
                        <p className="mt-1 font-mono text-lg">{invoice.code}</p>
                    </div>
                </header>

                <section className="grid grid-cols-1 gap-6 border-b py-6 text-sm sm:grid-cols-2">
                    <div>
                        <p className="text-xs text-muted-foreground">Cliente</p>
                        <p className="font-medium">{invoice.customer.name}</p>
                        {invoice.customer.document_number && (
                            <p className="text-muted-foreground">
                                {invoice.customer.document_type?.toUpperCase()} {invoice.customer.document_number}
                            </p>
                        )}
                        {invoice.customer.address && <p className="text-muted-foreground">{invoice.customer.address}</p>}
                    </div>
                    <div className="sm:text-right">
                        <p>
                            <span className="text-muted-foreground">Emisión:</span> {shortDate(invoice.issue_date)}
                        </p>
                        <p>
                            <span className="text-muted-foreground">Vencimiento:</span> {shortDate(invoice.due_date)}
                        </p>
                        <div className="mt-2 sm:flex sm:justify-end">
                            <InvoiceStatusBadge invoice={invoice} />
                        </div>
                    </div>
                </section>

                <table className="w-full py-6 text-sm">
                    <thead>
                        <tr className="border-b text-left text-xs text-muted-foreground uppercase">
                            <th className="py-3 font-medium">Descripción</th>
                            <th className="py-3 text-right font-medium">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr className="border-b">
                            <td className="py-4">{invoice.description}</td>
                            <td className="py-4 text-right tabular-nums">{money(invoice.subtotal, invoice.currency)}</td>
                        </tr>
                    </tbody>
                </table>

                <dl className="ml-auto mt-6 w-full max-w-xs space-y-1.5 text-sm">
                    <div className="flex justify-between">
                        <dt className="text-muted-foreground">Op. gravada</dt>
                        <dd className="tabular-nums">{money(invoice.subtotal, invoice.currency)}</dd>
                    </div>
                    <div className="flex justify-between">
                        <dt className="text-muted-foreground">IGV (18%)</dt>
                        <dd className="tabular-nums">{money(invoice.igv, invoice.currency)}</dd>
                    </div>
                    <div className="flex justify-between border-t pt-2 text-base font-semibold">
                        <dt>Total</dt>
                        <dd className="tabular-nums">{money(invoice.total, invoice.currency)}</dd>
                    </div>
                    {Number(invoice.paid) > 0 && (
                        <div className="flex justify-between text-muted-foreground">
                            <dt>Pagado</dt>
                            <dd className="tabular-nums">{money(invoice.paid, invoice.currency)}</dd>
                        </div>
                    )}
                </dl>

                {invoice.void_reason && <p className="mt-6 text-sm text-error">Anulado: {invoice.void_reason}</p>}
            </article>
        </div>
    );
}
