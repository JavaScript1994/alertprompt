import { createColumnHelper } from '@tanstack/react-table';
import { Building2, ChevronRight, Plus, Search, User as UserIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import DataTable from '@/components/shared/DataTable';
import EmptyState from '@/components/shared/EmptyState';
import PageHeader from '@/components/shared/PageHeader';
import Pagination from '@/components/shared/Pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { useClients } from '@/hooks/useClients';
import { useCan } from '@/hooks/usePermissions';
import type { Client, TenantType } from '@/types';
import ClientFormDialog from './ClientFormDialog';
import { CLIENT_STATUS, CLIENT_TYPE_LABELS, DOCUMENT_LABELS } from './clientLabels';

const columnHelper = createColumnHelper<Client>();

export default function Clients({ type }: { type: TenantType }) {
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState('');
    const [submittedSearch, setSubmittedSearch] = useState('');
    const [isCreating, setIsCreating] = useState(false);
    const { data, isLoading, isFetching } = useClients({ type, page, search: submittedSearch || undefined });
    const can = useCan();
    const navigate = useNavigate();
    const labels = CLIENT_TYPE_LABELS[type];

    const columns = useMemo(
        () => [
            columnHelper.accessor('name', {
                header: type === 'company' ? 'Razón social' : 'Nombre',
                cell: (info) => {
                    const client = info.row.original;
                    return (
                        <Link to={`/admin/clients/${client.id}`} className="block max-w-[280px]">
                            <p className="truncate font-medium text-foreground hover:text-primary">{client.name}</p>
                            {client.contact_email && <p className="truncate text-xs text-muted-foreground">{client.contact_email}</p>}
                        </Link>
                    );
                },
            }),
            columnHelper.accessor('document_number', {
                header: 'Documento',
                cell: (info) => {
                    const client = info.row.original;
                    return client.document_type ? (
                        <span className="font-mono text-xs">
                            {DOCUMENT_LABELS[client.document_type]} {client.document_number}
                        </span>
                    ) : (
                        <span className="text-muted-foreground">—</span>
                    );
                },
            }),
            columnHelper.accessor('status', {
                header: 'Estado',
                cell: (info) => <Badge variant={CLIENT_STATUS[info.getValue()].variant}>{CLIENT_STATUS[info.getValue()].label}</Badge>,
            }),
            columnHelper.accessor('plan_name', { header: 'Plan' }),
            columnHelper.accessor('users_count', { header: 'Usuarios', meta: { className: 'text-right tabular-nums' } }),
            columnHelper.accessor('contacts_count', {
                header: 'Contactos',
                meta: { className: 'text-right tabular-nums' },
                cell: (info) => info.getValue().toLocaleString('es-PE'),
            }),
            columnHelper.display({
                id: 'go',
                header: '',
                meta: { className: 'w-[1%] text-right' },
                cell: (info) => (
                    <Button variant="ghost" size="icon" asChild>
                        <Link to={`/admin/clients/${info.row.original.id}`} aria-label={`Ver ${info.row.original.name}`}>
                            <ChevronRight />
                        </Link>
                    </Button>
                ),
            }),
        ],
        [type],
    );

    return (
        <div>
            <PageHeader
                title={labels.plural}
                description={
                    type === 'company'
                        ? 'Empresas con RUC que usan AlertPrompt para comunicarse con sus clientes.'
                        : 'Personas naturales (DNI, CE o RUC 10) con cuenta en AlertPrompt.'
                }
                actions={
                    can('admin.clients.create') && (
                        <Button onClick={() => setIsCreating(true)}>
                            <Plus />
                            Nueva {labels.singular.toLowerCase()}
                        </Button>
                    )
                }
            />

            <form
                className="mb-4 flex max-w-md gap-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    setPage(1);
                    setSubmittedSearch(search.trim());
                }}
            >
                <div className="relative flex-1">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Buscar por nombre, documento o correo"
                        className="pl-9"
                    />
                </div>
                <Button type="submit" variant="outline">
                    Buscar
                </Button>
            </form>

            {isLoading ? (
                <Skeleton className="h-64 w-full rounded-xl" />
            ) : data && data.data.length > 0 ? (
                <>
                    <DataTable data={data.data} columns={columns} minWidth="min-w-[820px]" dimmed={isFetching} />
                    <Pagination meta={data.meta} noun={labels.plural.toLowerCase()} onPageChange={setPage} />
                </>
            ) : (
                <EmptyState
                    icon={type === 'company' ? Building2 : UserIcon}
                    title={submittedSearch ? 'Sin resultados' : `Aún no hay ${labels.plural.toLowerCase()}`}
                    description={submittedSearch ? 'Prueba con otro nombre o número de documento.' : undefined}
                />
            )}

            <ClientFormDialog
                open={isCreating}
                type={type}
                onClose={() => setIsCreating(false)}
                onCreated={(client) => navigate(`/admin/clients/${client.id}`)}
            />
        </div>
    );
}
