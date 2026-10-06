import {
    createColumnHelper,
    flexRender,
    getCoreRowModel,
    getSortedRowModel,
    useReactTable,
    type SortingState,
} from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ArrowUpDown, ChevronLeft, ChevronRight, Pencil, Play, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import Badge, { type BadgeVariant } from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import ChannelBadge from '@/components/ui/ChannelBadge';
import type { Campaign, PaginatedResponse } from '@/types';

const STATUS_LABELS: Record<Campaign['status'], string> = {
    draft: 'Borrador',
    scheduled: 'Programada',
    running: 'En curso',
    paused: 'Pausada',
    completed: 'Completada',
    cancelled: 'Cancelada',
};

const STATUS_VARIANTS: Record<Campaign['status'], BadgeVariant> = {
    draft: 'neutral',
    scheduled: 'info',
    running: 'success',
    paused: 'warning',
    completed: 'brand',
    cancelled: 'error',
};

function CampaignProgress({ campaign }: { campaign: Campaign }) {
    const { stats } = campaign;
    const delivered = stats.delivered + stats.read;
    const pct = stats.total > 0 ? Math.round(((delivered + stats.failed + stats.skipped) / stats.total) * 100) : 0;
    const pctOf = (n: number) => (stats.total > 0 ? `${(n / stats.total) * 100}%` : '0%');

    return (
        <div
            title={`${delivered} entregados · ${stats.failed} fallidos · ${stats.skipped} omitidos · ${stats.total} total`}
        >
            <div className="flex h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                <div className="h-full bg-whatsapp-500" style={{ width: pctOf(delivered) }} />
                <div className="h-full bg-red-400" style={{ width: pctOf(stats.failed) }} />
                <div className="h-full bg-slate-300" style={{ width: pctOf(stats.skipped) }} />
            </div>
            <p className="mt-1 truncate text-xs text-slate-500">
                {stats.total} total · {pct}%
            </p>
        </div>
    );
}

const columnHelper = createColumnHelper<Campaign>();

export default function CampaignsTable({
    data,
    onDispatch,
    dispatchPendingId,
    onEdit,
    onDelete,
    deletePendingId,
    onPageChange,
}: {
    data: PaginatedResponse<Campaign>;
    onDispatch: (campaign: Campaign) => void;
    dispatchPendingId: number | null;
    onEdit: (campaign: Campaign) => void;
    onDelete: (campaign: Campaign) => void;
    deletePendingId: number | null;
    onPageChange: (page: number) => void;
}) {
    const [sorting, setSorting] = useState<SortingState>([]);

    const columns = useMemo(
        () => [
            columnHelper.accessor('name', {
                header: 'Campaña',
                cell: (info) => {
                    const campaign = info.row.original;
                    return (
                        <div className="max-w-[200px]">
                            <p className="truncate text-sm font-medium text-ink-900">{campaign.name}</p>
                            <div className="mt-1">
                                <ChannelBadge channel={campaign.channel} />
                            </div>
                            <p className="mt-1 truncate text-xs text-slate-500">
                                {campaign.template.name}
                                {campaign.status === 'scheduled' && campaign.scheduled_at && (
                                    <>
                                        {' '}
                                        ·{' '}
                                        {new Date(campaign.scheduled_at).toLocaleString('es-PE', {
                                            dateStyle: 'short',
                                            timeStyle: 'short',
                                        })}
                                    </>
                                )}
                            </p>
                        </div>
                    );
                },
            }),
            columnHelper.accessor('status', {
                header: 'Estado',
                cell: (info) => (
                    <Badge variant={STATUS_VARIANTS[info.getValue()]}>{STATUS_LABELS[info.getValue()]}</Badge>
                ),
                sortingFn: (a, b) => STATUS_LABELS[a.original.status].localeCompare(STATUS_LABELS[b.original.status]),
            }),
            columnHelper.display({
                id: 'progress',
                header: 'Progreso',
                enableSorting: false,
                cell: (info) => <CampaignProgress campaign={info.row.original} />,
            }),
            columnHelper.display({
                id: 'actions',
                header: 'Acciones',
                enableSorting: false,
                cell: (info) => {
                    const campaign = info.row.original;
                    if (campaign.status !== 'draft' && campaign.status !== 'scheduled') return null;

                    return (
                        <div className="flex flex-nowrap items-center justify-end gap-1.5">
                            <Button
                                variant="success"
                                size="sm"
                                className="whitespace-nowrap"
                                onClick={() => onDispatch(campaign)}
                                loading={dispatchPendingId === campaign.id}
                            >
                                {dispatchPendingId !== campaign.id && <Play className="h-3 w-3" />}
                                Iniciar
                            </Button>
                            <button
                                onClick={() => onEdit(campaign)}
                                title="Editar campaña"
                                className="shrink-0 rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                            >
                                <Pencil className="h-4 w-4" />
                            </button>
                            <button
                                onClick={() => onDelete(campaign)}
                                disabled={deletePendingId === campaign.id}
                                title="Eliminar campaña"
                                className="shrink-0 rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
                            >
                                <Trash2 className="h-4 w-4" />
                            </button>
                        </div>
                    );
                },
            }),
        ],
        [onDispatch, dispatchPendingId, onEdit, onDelete, deletePendingId],
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
        status: 'w-32',
        progress: 'w-36',
        actions: 'w-52',
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
                        Página {data.meta.current_page} de {data.meta.last_page} · {data.meta.total} campañas
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
