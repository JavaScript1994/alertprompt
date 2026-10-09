import { createColumnHelper } from '@tanstack/react-table';
import { Eye } from 'lucide-react';
import { useMemo, useState } from 'react';
import ChannelBadge from '@/components/shared/ChannelBadge';
import DataTable from '@/components/shared/DataTable';
import EmptyState from '@/components/shared/EmptyState';
import Pagination from '@/components/shared/Pagination';
import { CampaignStatusBadge, TemplateStatusBadge } from '@/components/shared/StatusBadge';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { useClientActivity, useClientUsers, useSupervision } from '@/hooks/useClients';
import { formatDateTime } from '@/lib/format';
import type { AuditLogEntry, Campaign, Contact, Template, TenantUser } from '@/types';
import { AUDIT_ACTION_LABELS } from './clientLabels';

function ReadOnlyNote() {
    return (
        <p className="mb-3 flex items-center gap-1.5 text-xs text-muted-foreground">
            <Eye className="size-3.5" />
            Solo lectura. Para hacer cambios, entra al panel del cliente.
        </p>
    );
}

const userColumn = createColumnHelper<TenantUser>();

export function ClientUsersTab({ clientId }: { clientId: number }) {
    const { data, isLoading } = useClientUsers(clientId);
    const columns = useMemo(
        () => [
            userColumn.accessor('name', {
                header: 'Usuario',
                cell: (info) => (
                    <div>
                        <p className="font-medium text-foreground">{info.getValue()}</p>
                        <p className="text-xs text-muted-foreground">{info.row.original.email}</p>
                    </div>
                ),
            }),
            userColumn.accessor('roles', {
                header: 'Rol',
                cell: (info) => info.getValue().map((role) => role.label).join(', ') || '—',
            }),
            userColumn.accessor('email_verified_at', {
                header: 'Estado',
                cell: (info) =>
                    info.row.original.deactivated_at ? (
                        <Badge variant="neutral">Desactivado</Badge>
                    ) : info.getValue() ? (
                        <Badge variant="success">Activo</Badge>
                    ) : (
                        <Badge variant="warning">Invitación pendiente</Badge>
                    ),
            }),
        ],
        [],
    );

    if (isLoading) return <Skeleton className="h-40 w-full rounded-xl" />;
    return <DataTable data={data ?? []} columns={columns} minWidth="min-w-[560px]" />;
}

const contactColumn = createColumnHelper<Contact>();
const templateColumn = createColumnHelper<Template>();
const campaignColumn = createColumnHelper<Campaign>();

export function ClientContactsTab({ clientId }: { clientId: number }) {
    const [page, setPage] = useState(1);
    const { data, isLoading, isFetching } = useSupervision(clientId, 'contacts', page);
    const columns = useMemo(
        () => [
            contactColumn.accessor('name', { header: 'Nombre' }),
            contactColumn.accessor('phone', { header: 'Teléfono', cell: (info) => info.getValue() ?? '—' }),
            contactColumn.accessor('email', { header: 'Email', cell: (info) => info.getValue() ?? '—' }),
        ],
        [],
    );

    if (isLoading) return <Skeleton className="h-40 w-full rounded-xl" />;
    if (!data || data.data.length === 0) return <EmptyState icon={Eye} title="El cliente aún no tiene contactos" />;

    return (
        <>
            <ReadOnlyNote />
            <DataTable data={data.data} columns={columns} dimmed={isFetching} />
            <Pagination meta={data.meta} noun="contactos" onPageChange={setPage} />
        </>
    );
}

export function ClientTemplatesTab({ clientId }: { clientId: number }) {
    const [page, setPage] = useState(1);
    const { data, isLoading, isFetching } = useSupervision(clientId, 'templates', page);
    const columns = useMemo(
        () => [
            templateColumn.accessor('name', {
                header: 'Plantilla',
                cell: (info) => (
                    <div className="max-w-[320px]">
                        <p className="font-medium text-foreground">{info.getValue()}</p>
                        <p className="line-clamp-1 text-xs text-muted-foreground">{info.row.original.body}</p>
                    </div>
                ),
            }),
            templateColumn.accessor('channel', { header: 'Canal', cell: (info) => <ChannelBadge channel={info.getValue()} /> }),
            templateColumn.accessor('category', { header: 'Categoría', cell: (info) => <span className="capitalize">{info.getValue()}</span> }),
            templateColumn.accessor('status', { header: 'Estado', cell: (info) => <TemplateStatusBadge status={info.getValue()} /> }),
        ],
        [],
    );

    if (isLoading) return <Skeleton className="h-40 w-full rounded-xl" />;
    if (!data || data.data.length === 0) return <EmptyState icon={Eye} title="El cliente aún no tiene plantillas" />;

    return (
        <>
            <ReadOnlyNote />
            <DataTable data={data.data} columns={columns} dimmed={isFetching} />
            <Pagination meta={data.meta} noun="plantillas" onPageChange={setPage} />
        </>
    );
}

export function ClientCampaignsTab({ clientId }: { clientId: number }) {
    const [page, setPage] = useState(1);
    const { data, isLoading, isFetching } = useSupervision(clientId, 'campaigns', page);
    const columns = useMemo(
        () => [
            campaignColumn.accessor('name', { header: 'Campaña' }),
            campaignColumn.accessor('channel', { header: 'Canal', cell: (info) => <ChannelBadge channel={info.getValue()} /> }),
            campaignColumn.accessor('status', { header: 'Estado', cell: (info) => <CampaignStatusBadge status={info.getValue()} /> }),
            campaignColumn.display({
                id: 'delivered',
                header: 'Entregados',
                meta: { className: 'text-right tabular-nums' },
                cell: (info) => {
                    const stats = info.row.original.stats;
                    return `${(stats.delivered + stats.read).toLocaleString('es-PE')} / ${stats.total.toLocaleString('es-PE')}`;
                },
            }),
            campaignColumn.accessor('created_at', { header: 'Creada', cell: (info) => formatDateTime(info.getValue(), 'short') }),
        ],
        [],
    );

    if (isLoading) return <Skeleton className="h-40 w-full rounded-xl" />;
    if (!data || data.data.length === 0) return <EmptyState icon={Eye} title="El cliente aún no tiene campañas" />;

    return (
        <>
            <ReadOnlyNote />
            <DataTable data={data.data} columns={columns} dimmed={isFetching} />
            <Pagination meta={data.meta} noun="campañas" onPageChange={setPage} />
        </>
    );
}

const activityColumn = createColumnHelper<AuditLogEntry>();

export function ClientActivityTab({ clientId }: { clientId: number }) {
    const [page, setPage] = useState(1);
    const { data, isLoading, isFetching } = useClientActivity(clientId, page);
    const columns = useMemo(
        () => [
            activityColumn.accessor('created_at', { header: 'Fecha', cell: (info) => formatDateTime(info.getValue(), 'short') }),
            activityColumn.accessor('action', {
                header: 'Acción',
                cell: (info) => {
                    const entry = info.row.original;
                    const detail =
                        entry.action === 'impersonation.request'
                            ? `${String(entry.metadata.method)} /${String(entry.metadata.path)}`
                            : entry.action === 'client.suspended' && entry.metadata.reason
                              ? String(entry.metadata.reason)
                              : null;
                    return (
                        <div>
                            <p className="font-medium text-foreground">{AUDIT_ACTION_LABELS[entry.action] ?? entry.action}</p>
                            {detail && <p className="font-mono text-xs text-muted-foreground">{detail}</p>}
                        </div>
                    );
                },
            }),
            activityColumn.accessor('user', { header: 'Por', cell: (info) => info.getValue()?.name ?? 'Sistema' }),
            activityColumn.accessor('ip', { header: 'IP', cell: (info) => <span className="font-mono text-xs">{info.getValue() ?? '—'}</span> }),
        ],
        [],
    );

    if (isLoading) return <Skeleton className="h-40 w-full rounded-xl" />;
    if (!data || data.data.length === 0) return <EmptyState icon={Eye} title="Sin actividad registrada" />;

    return (
        <>
            <DataTable data={data.data} columns={columns} dimmed={isFetching} />
            <Pagination meta={data.meta} noun="registros" onPageChange={setPage} />
        </>
    );
}
