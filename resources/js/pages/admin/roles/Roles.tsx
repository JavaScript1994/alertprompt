import { createColumnHelper } from '@tanstack/react-table';
import { Eye, Lock, Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import ConfirmDialog from '@/components/shared/ConfirmDialog';
import DataTable from '@/components/shared/DataTable';
import EmptyState from '@/components/shared/EmptyState';
import PageHeader from '@/components/shared/PageHeader';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useCan } from '@/hooks/usePermissions';
import { useDeleteRole, useRoles } from '@/hooks/useRoles';
import { apiErrorMessage } from '@/lib/format';
import type { Role } from '@/types';

const columnHelper = createColumnHelper<Role>();

export const ROLE_SCOPE_LABELS = { platform: 'Plataforma', client: 'Cliente' } as const;

export default function Roles() {
    const { data: roles, isLoading } = useRoles();
    const deleteRole = useDeleteRole();
    const can = useCan();
    const canManage = can('admin.roles.manage');
    const [deleting, setDeleting] = useState<Role | null>(null);

    const confirmDelete = () => {
        if (!deleting) return;
        deleteRole.mutate(deleting.id, { onSuccess: () => setDeleting(null) });
    };

    const columns = useMemo(
        () => [
            columnHelper.accessor('label', {
                header: 'Rol',
                cell: (info) => {
                    const role = info.row.original;
                    return (
                        <div className="max-w-[320px]">
                            <p className="flex items-center gap-1.5 font-medium text-foreground">
                                {role.label}
                                {role.is_locked && <Lock className="size-3.5 text-muted-foreground" aria-label="Bloqueado" />}
                            </p>
                            {role.description && (
                                <p className="mt-0.5 line-clamp-2 text-xs text-muted-foreground">{role.description}</p>
                            )}
                        </div>
                    );
                },
            }),
            columnHelper.accessor('scope', {
                header: 'Panel',
                cell: (info) => (
                    <Badge variant={info.getValue() === 'platform' ? 'primary' : 'info'}>
                        {ROLE_SCOPE_LABELS[info.getValue()]}
                    </Badge>
                ),
            }),
            columnHelper.accessor('is_system', {
                header: 'Tipo',
                cell: (info) => (
                    <Badge variant={info.getValue() ? 'neutral' : 'outline'}>
                        {info.getValue() ? 'Sistema' : 'Personalizado'}
                    </Badge>
                ),
            }),
            columnHelper.accessor('users_count', {
                header: 'Usuarios',
                meta: { className: 'text-right tabular-nums' },
            }),
            columnHelper.accessor('permissions_count', {
                header: 'Permisos',
                meta: { className: 'text-right tabular-nums' },
            }),
            columnHelper.display({
                id: 'actions',
                header: '',
                meta: { className: 'w-[1%] whitespace-nowrap text-right' },
                cell: (info) => {
                    const role = info.row.original;
                    const canEdit = canManage && !role.is_locked;

                    return (
                        <div className="flex justify-end gap-1">
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button variant="ghost" size="icon" asChild>
                                        <Link
                                            to={`/admin/roles/${role.id}`}
                                            aria-label={`${canEdit ? 'Editar' : 'Ver'} ${role.label}`}
                                        >
                                            {canEdit ? <Pencil /> : <Eye />}
                                        </Link>
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>{canEdit ? 'Editar' : 'Ver'}</TooltipContent>
                            </Tooltip>
                            {/* Los roles de sistema nunca se eliminan: ni se ofrece la acción. */}
                            {canManage && !role.is_system && (
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        {/* span: un botón deshabilitado no dispara el tooltip. */}
                                        <span>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="text-error hover:text-error"
                                                disabled={role.users_count > 0}
                                                onClick={() => setDeleting(role)}
                                                aria-label={`Eliminar ${role.label}`}
                                            >
                                                <Trash2 />
                                            </Button>
                                        </span>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        {role.users_count > 0 ? 'Tiene usuarios asignados' : 'Eliminar'}
                                    </TooltipContent>
                                </Tooltip>
                            )}
                        </div>
                    );
                },
            }),
        ],
        [canManage],
    );

    return (
        <div>
            <PageHeader
                title="Roles y permisos"
                description="Crea roles combinando permisos del árbol. Los roles de cliente se asignan a usuarios de empresas y personas naturales."
                actions={
                    canManage && (
                        <Button asChild>
                            <Link to="/admin/roles/new">
                                <Plus />
                                Nuevo rol
                            </Link>
                        </Button>
                    )
                }
            />

            {deleteRole.isError && (
                <Alert variant="error" className="mb-4">
                    <AlertTitle>{apiErrorMessage(deleteRole.error, ['role'], 'No se pudo eliminar el rol.')}</AlertTitle>
                </Alert>
            )}

            {isLoading ? (
                <Skeleton className="h-64 w-full rounded-xl" />
            ) : roles && roles.length > 0 ? (
                <DataTable data={roles} columns={columns} minWidth="min-w-[720px]" />
            ) : (
                <EmptyState icon={ShieldCheck} title="Aún no hay roles" />
            )}

            <ConfirmDialog
                open={deleting !== null}
                title="Eliminar rol"
                description={
                    <>
                        Se eliminará el rol <strong>{deleting?.label}</strong>. Esta acción no se puede deshacer.
                    </>
                }
                confirmLabel="Eliminar"
                destructive
                loading={deleteRole.isPending}
                onConfirm={confirmDelete}
                onCancel={() => setDeleting(null)}
            />
        </div>
    );
}
