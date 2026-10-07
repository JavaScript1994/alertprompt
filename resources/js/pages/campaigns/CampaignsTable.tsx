import { createColumnHelper } from '@tanstack/react-table';
import { Pencil, Play, Trash2 } from 'lucide-react';
import { useMemo } from 'react';
import ChannelBadge from '@/components/shared/ChannelBadge';
import DataTable from '@/components/shared/DataTable';
import Pagination from '@/components/shared/Pagination';
import { CampaignStatusBadge, campaignStatusLabel } from '@/components/shared/StatusBadge';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { formatDateTime } from '@/lib/format';
import type { Campaign, PaginatedResponse } from '@/types';

function CampaignProgress({ campaign }: { campaign: Campaign }) {
    const { stats } = campaign;
    const delivered = stats.delivered + stats.read;
    const pct = stats.total > 0 ? Math.round(((delivered + stats.failed + stats.skipped) / stats.total) * 100) : 0;
    const pctOf = (n: number) => (stats.total > 0 ? `${(n / stats.total) * 100}%` : '0%');

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <div className="cursor-default">
                    <div className="flex h-1.5 w-full overflow-hidden rounded-full bg-muted">
                        <div className="h-full bg-whatsapp-500" style={{ width: pctOf(delivered) }} />
                        <div className="h-full bg-error" style={{ width: pctOf(stats.failed) }} />
                        <div className="h-full bg-muted-foreground/40" style={{ width: pctOf(stats.skipped) }} />
                    </div>
                    <p className="mt-1 truncate text-xs text-muted-foreground">
                        {stats.total} total · {pct}%
                    </p>
                </div>
            </TooltipTrigger>
            <TooltipContent>
                {delivered} entregados · {stats.failed} fallidos · {stats.skipped} omitidos · {stats.total} total
            </TooltipContent>
        </Tooltip>
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
    const columns = useMemo(
        () => [
            columnHelper.accessor('name', {
                header: 'Campaña',
                cell: (info) => {
                    const campaign = info.row.original;
                    return (
                        <div className="max-w-[220px]">
                            <p className="truncate font-medium text-foreground">{campaign.name}</p>
                            <ChannelBadge channel={campaign.channel} className="mt-1" />
                            <p className="mt-1 truncate text-xs text-muted-foreground">
                                {campaign.template.name}
                                {campaign.status === 'scheduled' &&
                                    campaign.scheduled_at &&
                                    ` · ${formatDateTime(campaign.scheduled_at, 'short')}`}
                            </p>
                        </div>
                    );
                },
            }),
            columnHelper.accessor('status', {
                header: 'Estado',
                meta: { className: 'w-32' },
                cell: (info) => <CampaignStatusBadge status={info.getValue()} />,
                sortingFn: (a, b) =>
                    campaignStatusLabel(a.original.status).localeCompare(campaignStatusLabel(b.original.status)),
            }),
            columnHelper.display({
                id: 'progress',
                header: 'Progreso',
                meta: { className: 'w-36' },
                cell: (info) => <CampaignProgress campaign={info.row.original} />,
            }),
            columnHelper.display({
                id: 'actions',
                header: () => <span className="sr-only">Acciones</span>,
                meta: { className: 'w-44 text-right' },
                cell: (info) => {
                    const campaign = info.row.original;
                    if (campaign.status !== 'draft' && campaign.status !== 'scheduled') return null;

                    return (
                        <div className="inline-flex flex-nowrap items-center gap-1">
                            <Button
                                variant="success"
                                size="sm"
                                onClick={() => onDispatch(campaign)}
                                loading={dispatchPendingId === campaign.id}
                            >
                                {dispatchPendingId !== campaign.id && <Play />}
                                Iniciar
                            </Button>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label="Editar campaña"
                                        onClick={() => onEdit(campaign)}
                                    >
                                        <Pencil />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>Editar campaña</TooltipContent>
                            </Tooltip>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="ghosterror"
                                        size="icon-sm"
                                        aria-label="Eliminar campaña"
                                        onClick={() => onDelete(campaign)}
                                        disabled={deletePendingId === campaign.id}
                                    >
                                        <Trash2 />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>Eliminar campaña</TooltipContent>
                            </Tooltip>
                        </div>
                    );
                },
            }),
        ],
        [onDispatch, dispatchPendingId, onEdit, onDelete, deletePendingId],
    );

    return (
        <div>
            <DataTable data={data.data} columns={columns} minWidth="min-w-[680px]" />
            <Pagination meta={data.meta} noun="campañas" onPageChange={onPageChange} />
        </div>
    );
}
