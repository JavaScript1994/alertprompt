import { Printer } from 'lucide-react';
import { Link, useParams } from 'react-router-dom';
import Logo from '@/components/shared/Logo';
import { money, shortDate } from '@/components/shared/InvoiceBits';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useCreditNote } from '@/hooks/useBilling';

/** Nota de crédito imprimible. Si SUNAT no la aceptó, se rotula como documento interno. */
export default function CreditNotePrint({ scope }: { scope: 'client' | 'admin' }) {
    const id = Number(useParams<{ id: string }>().id);
    const { data: note, isLoading } = useCreditNote(id, scope);

    if (isLoading || !note) return <Skeleton className="h-[600px] w-full rounded-xl" />;

    const invoicePath = `${scope === 'admin' ? '/admin' : '/billing'}/invoices/${note.invoice.id}`;

    return (
        <div className="mx-auto max-w-3xl">
            <div className="mb-4 flex justify-end print:hidden">
                <Button variant="outline" onClick={() => window.print()}>
                    <Printer />
                    Imprimir o guardar PDF
                </Button>
            </div>

            {!note.is_electronic && (
                <Alert variant="info" className="mb-4 print:hidden">
                    <AlertDescription>
                        Documento interno. Aún no es una nota de crédito electrónica válida ante SUNAT: se emitirá cuando se
                        conecte la facturación electrónica.
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
                        <p className="text-sm font-semibold tracking-wide uppercase">{note.is_electronic ? 'Nota de crédito electrónica' : 'Nota de crédito'}</p>
                        <p className="mt-1 font-mono text-lg">{note.code}</p>
                    </div>
                </header>

                <section className="grid grid-cols-1 gap-6 border-b py-6 text-sm sm:grid-cols-2">
                    <div>
                        <p className="text-xs text-muted-foreground">Cliente</p>
                        <p className="font-medium">{note.customer.name}</p>
                        {note.customer.document_number && (
                            <p className="text-muted-foreground">
                                {note.customer.document_type?.toUpperCase()} {note.customer.document_number}
                            </p>
                        )}
                        {note.customer.address && <p className="text-muted-foreground">{note.customer.address}</p>}
                    </div>
                    <div className="sm:text-right">
                        <p>
                            <span className="text-muted-foreground">Emisión:</span> {shortDate(note.issue_date)}
                        </p>
                        <p>
                            <span className="text-muted-foreground">Comprobante que corrige:</span>{' '}
                            <Link to={invoicePath} className="font-mono hover:text-primary print:no-underline">
                                {note.invoice.code}
                            </Link>
                        </p>
                        <p>
                            <span className="text-muted-foreground">Motivo:</span> {note.reason}
                        </p>
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
                        {note.lines.map((line, index) => (
                            <tr key={index} className="border-b">
                                <td className="py-4">{line.description}</td>
                                <td className="py-4 text-right tabular-nums">{money(line.amount, note.currency)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                <dl className="ml-auto mt-6 w-full max-w-xs space-y-1.5 text-sm">
                    <div className="flex justify-between">
                        <dt className="text-muted-foreground">Op. gravada</dt>
                        <dd className="tabular-nums">{money(note.subtotal, note.currency)}</dd>
                    </div>
                    <div className="flex justify-between">
                        <dt className="text-muted-foreground">IGV (18%)</dt>
                        <dd className="tabular-nums">{money(note.igv, note.currency)}</dd>
                    </div>
                    <div className="flex justify-between border-t pt-2 text-base font-semibold">
                        <dt>Total</dt>
                        <dd className="tabular-nums">{money(note.total, note.currency)}</dd>
                    </div>
                    <div className="flex justify-between text-muted-foreground">
                        <dt>Aplicado a comprobantes</dt>
                        <dd className="tabular-nums">{money(note.applied, note.currency)}</dd>
                    </div>
                    {Number(note.remaining) > 0 && (
                        <div className="flex justify-between font-medium text-success">
                            <dt>Saldo a favor</dt>
                            <dd className="tabular-nums">{money(note.remaining, note.currency)}</dd>
                        </div>
                    )}
                </dl>

                {Number(note.remaining) > 0 && (
                    <p className="mt-6 text-sm text-muted-foreground">El saldo a favor se descuenta automáticamente de tus próximos comprobantes.</p>
                )}
            </article>
        </div>
    );
}
