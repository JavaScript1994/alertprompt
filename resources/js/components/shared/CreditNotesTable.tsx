import { createColumnHelper } from '@tanstack/react-table';
import { Printer } from 'lucide-react';
import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import DataTable from '@/components/shared/DataTable';
import { money, shortDate } from '@/components/shared/InvoiceBits';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { CreditNote } from '@/types';

const column = createColumnHelper<CreditNote>();

/** Notas de crédito con lo que falta aplicar (saldo a favor). */
export default function CreditNotesTable({ notes, scope }: { notes: CreditNote[]; scope: 'client' | 'admin' }) {
    const base = scope === 'admin' ? '/admin' : '/billing';

    const columns = useMemo(
        () => [
            column.accessor('code', { header: 'Nota de crédito', cell: (info) => <span className="font-mono text-xs">{info.getValue()}</span> }),
            column.accessor('invoice', {
                header: 'Corrige',
                cell: (info) => (
                    <Link to={`${base}/invoices/${info.getValue().id}`} className="font-mono text-xs hover:text-primary">
                        {info.getValue().code}
                    </Link>
                ),
            }),
            column.accessor('reason', { header: 'Motivo', cell: (info) => <span className="block max-w-[240px] truncate">{info.getValue()}</span> }),
            column.accessor('issue_date', { header: 'Emisión', cell: (info) => shortDate(info.getValue()) }),
            column.accessor('total', { header: 'Total', meta: { className: 'text-right tabular-nums' }, cell: (info) => money(info.getValue()) }),
            column.accessor('remaining', {
                header: 'Saldo a favor',
                meta: { className: 'text-right tabular-nums' },
                cell: (info) => (Number(info.getValue()) > 0 ? money(info.getValue()) : <Badge variant="neutral">Aplicada</Badge>),
            }),
            column.display({
                id: 'print',
                header: '',
                meta: { className: 'w-[1%]' },
                cell: (info) => (
                    <Button variant="ghost" size="icon-sm" asChild>
                        <Link to={`${base}/credit-notes/${info.row.original.id}`} aria-label={`Ver ${info.row.original.code}`}>
                            <Printer />
                        </Link>
                    </Button>
                ),
            }),
        ],
        [base],
    );

    return <DataTable data={notes} columns={columns} minWidth="min-w-[760px]" />;
}
