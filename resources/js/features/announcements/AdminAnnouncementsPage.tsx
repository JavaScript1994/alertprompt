import { createColumnHelper } from '@tanstack/react-table';
import { BarChart3, CheckCircle2, Megaphone, Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import ConfirmDialog from '@/components/shared/ConfirmDialog';
import DataTable from '@/components/shared/DataTable';
import EmptyState from '@/components/shared/EmptyState';
import PageHeader from '@/components/shared/PageHeader';
import Pagination from '@/components/shared/Pagination';
import { Badge, type BadgeVariant } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useCan } from '@/hooks/usePermissions';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { useAdminAnnouncements, useAnnouncementStats, useDeleteAnnouncement, useResolveAnnouncement } from './api';
import AnnouncementEditor from './AnnouncementEditor';
import { ANNOUNCEMENT_TYPES } from './meta';
import type { AdminAnnouncement, AnnouncementStatus } from './types';

const STATUS: Record<AnnouncementStatus, { label: string; variant: BadgeVariant }> = {
    draft: { label: 'Borrador', variant: 'neutral' },
    scheduled: { label: 'Programado', variant: 'info' },
    live: { label: 'Publicado', variant: 'success' },
    expired: { label: 'Vencido', variant: 'neutral' },
    resolved: { label: 'Resuelto', variant: 'success' },
};

function audienceLabel(item: AdminAnnouncement): string {
    const scope =
        item.audience === 'all'
            ? 'Todos los clientes'
            : item.audience === 'plans'
              ? `Planes: ${item.plan_keys.join(', ')}`
              : item.tenants.length === 1
                ? item.tenants[0]?.name ?? '1 cliente'
                : `${item.tenants.length} clientes`;
    return item.admins_only ? `${scope} · solo administradores` : scope;
}

function StatsDialog({ item, onClose }: { item: AdminAnnouncement; onClose: () => void }) {
    const { data, isLoading } = useAnnouncementStats(item.id);
    const total = (data ?? []).reduce((sum, row) => ({ recipients: sum.recipients + row.recipients, reads: sum.reads + row.reads }), { recipients: 0, reads: 0 });

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Lecturas</DialogTitle>
                    <DialogDescription>{item.title}</DialogDescription>
                </DialogHeader>
                {isLoading ? (
                    <Skeleton className="h-32 w-full" />
                ) : (
                    <div className="space-y-3">
                        <p className="text-sm text-muted-foreground">
                            {total.reads} de {total.recipients} usuarios lo leyeron
                            {total.recipients > 0 && ` (${Math.round((total.reads / total.recipients) * 100)}%)`}.
                        </p>
                        <ul className="max-h-80 divide-y overflow-y-auto rounded-lg border">
                            {(data ?? []).map((row) => {
                                const pct = row.recipients > 0 ? Math.round((row.reads / row.recipients) * 100) : 0;
                                return (
                                    <li key={row.tenant_id} className="space-y-1.5 px-3 py-2.5 text-sm">
                                        <div className="flex justify-between gap-3">
                                            <span className="truncate text-foreground">{row.tenant_name}</span>
                                            <span className="shrink-0 text-muted-foreground tabular-nums">
                                                {row.reads}/{row.recipients}
                                            </span>
                                        </div>
                                        <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                                            <div className="h-full rounded-full bg-primary dark:bg-brand-200" style={{ width: `${pct}%` }} />
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}

const column = createColumnHelper<AdminAnnouncement>();

/** Administración → Comunicados. */
export default function AdminAnnouncementsPage() {
    const [page, setPage] = useState(1);
    const { data, isLoading, isFetching } = useAdminAnnouncements(page);
    const canManage = useCan()('admin.announcements.manage');
    const resolve = useResolveAnnouncement();
    const remove = useDeleteAnnouncement();
    const [editing, setEditing] = useState<AdminAnnouncement | 'new' | null>(null);
    const [stats, setStats] = useState<AdminAnnouncement | null>(null);
    const [deleting, setDeleting] = useState<AdminAnnouncement | null>(null);

    const columns = useMemo(
        () => [
            column.accessor('title', {
                header: 'Comunicado',
                cell: (info) => {
                    const meta = ANNOUNCEMENT_TYPES[info.row.original.type];
                    return (
                        <div className="flex items-start gap-3">
                            <span className={cn('mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full', meta.tone)}>
                                <meta.icon className="size-4" />
                            </span>
                            <div className="min-w-0">
                                <p className="font-medium text-foreground">{info.getValue()}</p>
                                <p className="text-xs text-muted-foreground">
                                    {meta.label} · {audienceLabel(info.row.original)}
                                </p>
                            </div>
                        </div>
                    );
                },
            }),
            column.accessor('status', {
                header: 'Estado',
                cell: (info) => (
                    <div className="space-y-1">
                        <Badge variant={STATUS[info.getValue()].variant}>{STATUS[info.getValue()].label}</Badge>
                        {info.row.original.emailed_at && <p className="text-[0.7rem] text-muted-foreground">Enviado por correo</p>}
                    </div>
                ),
            }),
            column.accessor('published_at', {
                header: 'Publicación',
                cell: (info) => <span className="text-sm text-muted-foreground">{info.getValue() ? formatDateTime(info.getValue() as string, 'short') : '—'}</span>,
            }),
            column.accessor('reads_count', {
                header: 'Leído por',
                meta: { className: 'text-right' },
                cell: (info) => (
                    <button type="button" className="inline-flex items-center gap-1.5 text-sm tabular-nums hover:underline" onClick={() => setStats(info.row.original)}>
                        <BarChart3 className="size-3.5 text-muted-foreground" />
                        {info.getValue()}
                    </button>
                ),
            }),
            column.display({
                id: 'actions',
                header: () => <span className="sr-only">Acciones</span>,
                meta: { className: 'w-[1%] whitespace-nowrap text-right' },
                cell: (info) => {
                    const item = info.row.original;
                    if (!canManage) return null;
                    return (
                        <div className="inline-flex gap-1">
                            {item.type === 'incident' && item.status === 'live' && (
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghostsuccess"
                                            size="icon-sm"
                                            aria-label="Marcar resuelto"
                                            loading={resolve.isPending && resolve.variables === item.id}
                                            onClick={() => resolve.mutate(item.id)}
                                        >
                                            <CheckCircle2 />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Marcar resuelto (quita el banner)</TooltipContent>
                                </Tooltip>
                            )}
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button variant="ghost" size="icon-sm" aria-label="Editar" onClick={() => setEditing(item)}>
                                        <Pencil />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>Editar</TooltipContent>
                            </Tooltip>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button variant="ghosterror" size="icon-sm" aria-label="Eliminar" onClick={() => setDeleting(item)}>
                                        <Trash2 />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>Eliminar</TooltipContent>
                            </Tooltip>
                        </div>
                    );
                },
            }),
        ],
        [canManage, resolve],
    );

    return (
        <div>
            <PageHeader
                title="Comunicados"
                description="Novedades, avisos, incidentes y promociones para tus clientes."
                actions={
                    canManage && (
                        <Button onClick={() => setEditing('new')}>
                            <Plus />
                            Nuevo comunicado
                        </Button>
                    )
                }
            />

            {isLoading ? (
                <Skeleton className="h-64 w-full rounded-xl" />
            ) : data && data.data.length > 0 ? (
                <>
                    <DataTable data={data.data} columns={columns} dimmed={isFetching} minWidth="min-w-[760px]" />
                    <Pagination meta={data.meta} noun="comunicados" onPageChange={setPage} />
                </>
            ) : (
                <EmptyState
                    icon={Megaphone}
                    title="Aún no hay comunicados"
                    description="Anuncia nuevas funciones, mantenimientos o incidentes a todos tus clientes o a algunos."
                    action={
                        canManage && (
                            <Button onClick={() => setEditing('new')}>
                                <Plus />
                                Nuevo comunicado
                            </Button>
                        )
                    }
                />
            )}

            {editing && <AnnouncementEditor item={editing === 'new' ? undefined : editing} onClose={() => setEditing(null)} />}
            {stats && <StatsDialog item={stats} onClose={() => setStats(null)} />}
            <ConfirmDialog
                open={deleting !== null}
                title="Eliminar comunicado"
                description={`"${deleting?.title}" dejará de verse y se borran sus lecturas. Si ya se envió por correo, eso no se deshace.`}
                confirmLabel="Eliminar"
                destructive
                loading={remove.isPending}
                onConfirm={() => deleting && remove.mutate(deleting.id, { onSettled: () => setDeleting(null) })}
                onCancel={() => setDeleting(null)}
            />
        </div>
    );
}
