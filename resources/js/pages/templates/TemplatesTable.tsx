import {
    createColumnHelper,
    flexRender,
    getCoreRowModel,
    getSortedRowModel,
    useReactTable,
    type SortingState,
} from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ArrowUpDown, ChevronLeft, ChevronRight, ShieldCheck, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import Badge, { type BadgeVariant } from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import ChannelBadge from '@/components/ui/ChannelBadge';
import type { PaginatedResponse, Template, TemplateStatus } from '@/types';

const STATUS_LABELS: Record<TemplateStatus, string> = {
    draft: 'Borrador',
    pending_approval: 'Pendiente',
    approved: 'Aprobada',
    rejected: 'Rechazada',
    disabled: 'Deshabilitada',
};

const STATUS_VARIANTS: Record<TemplateStatus, BadgeVariant> = {
    draft: 'neutral',
    pending_approval: 'warning',
    approved: 'success',
    rejected: 'error',
    disabled: 'neutral',
};

const columnHelper = createColumnHelper<Template>();

export default function TemplatesTable({
    data,
    onApprove,
    approvePendingId,
    onDelete,
    deletePendingId,
    onPageChange,
}: {
    data: PaginatedResponse<Template>;
    onApprove: (template: Template) => void;
    approvePendingId: number | null;
    onDelete: (template: Template) => void;
    deletePendingId: number | null;
    onPageChange: (page: number) => void;
}) {
    const [sorting, setSorting] = useState<SortingState>([]);

    const columns = useMemo(
        () => [
            columnHelper.accessor('name', {
                header: 'Plantilla',
                cell: (info) => {
                    const template = info.row.original;
                    return (
                        <div className="max-w-[220px]">
                            <p className="truncate text-sm font-medium text-ink-900">{template.name}</p>
                            <p className="mt-0.5 line-clamp-1 text-xs text-slate-500">{template.body}</p>
                            {template.variables.length > 0 && (
                                <p className="mt-0.5 truncate text-xs text-slate-400">
                                    Variables: {template.variables.join(', ')}
                                </p>
                            )}
                        </div>
                    );
                },
            }),
            columnHelper.accessor('channel', {
                header: 'Canal',
                cell: (info) => <ChannelBadge channel={info.getValue()} />,
            }),
            columnHelper.accessor('category', {
                header: 'Categoría',
                cell: (info) => <span className="text-sm text-slate-600 capitalize">{info.getValue()}</span>,
            }),
            columnHelper.accessor('status', {
                header: 'Estado',
                cell: (info) => (
                    <Badge variant={STATUS_VARIANTS[info.getValue()]}>{STATUS_LABELS[info.getValue()]}</Badge>
                ),
                sortingFn: (a, b) => STATUS_LABELS[a.original.status].localeCompare(STATUS_LABELS[b.original.status]),
            }),
            columnHelper.display({
                id: 'actions',
                header: 'Acciones',
                enableSorting: false,
                cell: (info) => {
                    const template = info.row.original;
                    const canApprove = template.status === 'draft' || template.status === 'pending_approval';

                    return (
                        <div className="flex justify-end gap-1.5">
                            {canApprove && (
                                <button
                                    onClick={() => onApprove(template)}
                                    disabled={approvePendingId === template.id}
                                    title="Aprobar plantilla"
                                    className="shrink-0 rounded-md p-1.5 text-slate-400 hover:bg-whatsapp-50 hover:text-whatsapp-700 disabled:opacity-50"
                                >
                                    <ShieldCheck className="h-4 w-4" />
                                </button>
                            )}
                            <button
                                onClick={() => onDelete(template)}
                                disabled={deletePendingId === template.id}
                                title="Eliminar plantilla"
                                className="shrink-0 rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
                            >
                                <Trash2 className="h-4 w-4" />
                            </button>
                        </div>
                    );
                },
            }),
        ],
        [onApprove, approvePendingId, onDelete, deletePendingId],
    );

    const table = useReactTable({
        data: data.data,
        columns,
        state: { sorting },
        onSortingChange: setSorting,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
    });

    const COLUMN_WIDTHS: Record<string, string> = {
        channel: 'w-28',
        category: 'w-28',
        status: 'w-32',
        actions: 'w-24',
    };

    return (
        <div>
            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[680px] text-left text-sm">
                        <thead className="border-b border-slate-200 bg-slate-50 text-xs font-medium text-slate-500 uppercase">
                            {table.getHeaderGroups().map((headerGroup) => (
                                <tr key={headerGroup.id}>
                                    {headerGroup.headers.map((header) => {
                                        const sortDirection = header.column.getIsSorted();
                                        const widthClass = COLUMN_WIDTHS[header.column.id] ?? '';

                                        return (
                                            <th
                                                key={header.id}
                                                className={`px-4 py-2 ${header.column.id === 'actions' ? 'text-right' : ''} ${widthClass}`}
                                            >
                                                {header.column.getCanSort() ? (
                                                    <button
                                                        onClick={header.column.getToggleSortingHandler()}
                                                        className="inline-flex items-center gap-1 normal-case hover:text-slate-700"
                                                    >
                                                        {flexRender(header.column.columnDef.header, header.getContext())}
                                                        {sortDirection === 'asc' && <ArrowUp className="h-3 w-3" />}
                                                        {sortDirection === 'desc' && (
                                                            <ArrowDown className="h-3 w-3" />
                                                        )}
                                                        {!sortDirection && (
                                                            <ArrowUpDown className="h-3 w-3 text-slate-300" />
                                                        )}
                                                    </button>
                                                ) : (
                                                    flexRender(header.column.columnDef.header, header.getContext())
                                                )}
                                            </th>
                                        );
                                    })}
                                </tr>
                            ))}
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {table.getRowModel().rows.map((row) => (
                                <tr key={row.id} className="align-top transition-colors hover:bg-slate-50/70">
                                    {row.getVisibleCells().map((cell) => (
                                        <td
                                            key={cell.id}
                                            className={`px-4 py-2.5 ${cell.column.id === 'actions' ? 'text-right' : ''}`}
                                        >
                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {data.meta.last_page > 1 && (
                <div className="mt-4 flex items-center justify-between text-sm text-slate-600">
                    <span>
                        Página {data.meta.current_page} de {data.meta.last_page} · {data.meta.total} plantillas
                    </span>
                    <div className="flex gap-2">
                        <Button
                            variant="secondary"
                            size="sm"
                            onClick={() => onPageChange(Math.max(1, data.meta.current_page - 1))}
                            disabled={data.meta.current_page <= 1}
                        >
                            <ChevronLeft className="h-4 w-4" /> Anterior
                        </Button>
                        <Button
                            variant="secondary"
                            size="sm"
                            onClick={() => onPageChange(Math.min(data.meta.last_page, data.meta.current_page + 1))}
                            disabled={data.meta.current_page >= data.meta.last_page}
                        >
                            Siguiente <ChevronRight className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}
