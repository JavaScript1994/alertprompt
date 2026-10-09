import { createColumnHelper } from '@tanstack/react-table';
import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import DataTable from '@/components/shared/DataTable';
import { InvoiceStatusBadge, money, shortDate } from '@/components/shared/InvoiceBits';
import { useCan } from '@/hooks/usePermissions';
import type { Invoice } from '@/types';
import InvoiceActions from './InvoiceActions';

const column = createColumnHelper<Invoice>();

export default function InvoicesTable({ invoices, showClient = true }: { invoices: Invoice[]; showClient?: boolean }) {
    const canManage = useCan()('admin.billing.manage');

    const columns = useMemo(
        () => [
            column.accessor('code', { header: 'Comprobante', cell: (info) => <span className="font-mono text-xs">{info.getValue()}</span> }),
            ...(showClient
                ? [
                      column.accessor('tenant', {
                          header: 'Cliente',
                          cell: (info) =>
                              info.getValue() ? (
                                  <Link to={`/admin/clients/${info.getValue()?.id}`} className="hover:text-primary">
                                      {info.getValue()?.name}
                                  </Link>
                              ) : (
                                  '—'
                              ),
                      }),
                  ]
                : []),
            column.accessor('description', { header: 'Concepto', cell: (info) => <span className="block max-w-[240px] truncate">{info.getValue()}</span> }),
            column.accessor('due_date', { header: 'Vence', cell: (info) => shortDate(info.getValue()) }),
            column.accessor('total', { header: 'Total', meta: { className: 'text-right tabular-nums' }, cell: (info) => money(info.getValue()) }),
            column.accessor('balance', { header: 'Saldo', meta: { className: 'text-right tabular-nums' }, cell: (info) => money(info.getValue()) }),
            column.display({ id: 'status', header: 'Estado', cell: (info) => <InvoiceStatusBadge invoice={info.row.original} /> }),
            column.display({
                id: 'actions',
                header: '',
                meta: { className: 'w-[1%] whitespace-nowrap text-right' },
                cell: (info) => <InvoiceActions invoice={info.row.original} canManage={canManage} />,
            }),
        ],
        [showClient, canManage],
    );

    return <DataTable data={invoices} columns={columns} minWidth="min-w-[860px]" />;
}
