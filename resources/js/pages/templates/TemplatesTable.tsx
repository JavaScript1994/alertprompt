import { createColumnHelper } from '@tanstack/react-table';
import { ShieldCheck, Trash2 } from 'lucide-react';
import { useMemo } from 'react';
import ChannelBadge from '@/components/shared/ChannelBadge';
import DataTable from '@/components/shared/DataTable';
import Pagination from '@/components/shared/Pagination';
import { TemplateStatusBadge, templateStatusLabel } from '@/components/shared/StatusBadge';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import type { PaginatedResponse, Template } from '@/types';

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
    /** Sin permiso templates.update: no se muestra. */
    onApprove?: (template: Template) => void;
    approvePendingId: number | null;
    /** Sin permiso templates.delete: no se muestra. */
    onDelete?: (template: Template) => void;
    deletePendingId: number | null;
    onPageChange: (page: number) => void;
}) {
    const columns = useMemo(
        () => [
            columnHelper.accessor('name', {
                header: 'Plantilla',
                cell: (info) => {
                    const template = info.row.original;
                    return (
                        <div className="max-w-[200px]">
                            <p className="truncate font-medium text-foreground">{template.name}</p>
                            <p className="mt-0.5 line-clamp-1 text-xs text-muted-foreground">{template.body}</p>
                            {template.variables.length > 0 && (
                                <p className="mt-0.5 truncate font-mono text-xs text-muted-foreground/80">
                                    {template.variables.join(', ')}
                                </p>
                            )}
                        </div>
                    );
                },
            }),
            columnHelper.accessor('channel', {
                header: 'Canal',
                meta: { className: 'w-28' },
                cell: (info) => <ChannelBadge channel={info.getValue()} />,
            }),
            columnHelper.accessor('category', {
                header: 'Categoría',
                meta: { className: 'w-28' },
                cell: (info) => <span className="text-muted-foreground capitalize">{info.getValue()}</span>,
            }),
            columnHelper.accessor('status', {
                header: 'Estado',
                meta: { className: 'w-32' },
                cell: (info) => <TemplateStatusBadge status={info.getValue()} />,
                sortingFn: (a, b) =>
                    templateStatusLabel(a.original.status).localeCompare(templateStatusLabel(b.original.status)),
            }),
            columnHelper.display({
                id: 'actions',
                header: () => <span className="sr-only">Acciones</span>,
                meta: { className: 'w-24 text-right' },
                cell: (info) => {
                    const template = info.row.original;
                    const canApprove = onApprove !== undefined && (template.status === 'draft' || template.status === 'pending_approval');

                    return (
                        <div className="inline-flex gap-1">
                            {canApprove && (
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghostsuccess"
                                            size="icon-sm"
                                            aria-label="Aprobar plantilla"
                                            onClick={() => onApprove?.(template)}
                                            loading={approvePendingId === template.id}
                                        >
                                            {approvePendingId !== template.id && <ShieldCheck />}
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Aprobar plantilla</TooltipContent>
                                </Tooltip>
                            )}
                            {onDelete && (
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="ghosterror"
                                        size="icon-sm"
                                        aria-label="Eliminar plantilla"
                                        onClick={() => onDelete(template)}
                                        disabled={deletePendingId === template.id}
                                    >
                                        <Trash2 />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>Eliminar plantilla</TooltipContent>
                            </Tooltip>
                            )}
                        </div>
                    );
                },
            }),
        ],
        [onApprove, approvePendingId, onDelete, deletePendingId],
    );

    return (
        <div>
            <DataTable data={data.data} columns={columns} minWidth="min-w-[580px]" />
            <Pagination meta={data.meta} noun="plantillas" onPageChange={onPageChange} />
        </div>
    );
}
