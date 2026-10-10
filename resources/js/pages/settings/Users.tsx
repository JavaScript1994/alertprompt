import { createColumnHelper } from '@tanstack/react-table';
import { MailCheck, Pencil, Plus, UserCheck, UserX, Users as UsersIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import ConfirmDialog from '@/components/shared/ConfirmDialog';
import DataTable from '@/components/shared/DataTable';
import EmptyState from '@/components/shared/EmptyState';
import PageHeader from '@/components/shared/PageHeader';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useAuthUser } from '@/hooks/useAuth';
import { useCan } from '@/hooks/usePermissions';
import { useResetUserMfa } from '@/features/mfa/api';
import { MfaBadge, ResetMfaAction } from '@/features/mfa/UserMfaCells';
import { useResendInvitation, useSetUserActive, useTenantUsers } from '@/hooks/useUsers';
import { apiErrorMessage } from '@/lib/format';
import type { TenantUser } from '@/types';
import UserFormDialog from './UserFormDialog';

const columnHelper = createColumnHelper<TenantUser>();

function UserStatus({ user }: { user: TenantUser }) {
    if (user.deactivated_at) return <Badge variant="neutral">Desactivado</Badge>;
    if (!user.email_verified_at) return <Badge variant="warning">Invitación pendiente</Badge>;
    return <Badge variant="success">Activo</Badge>;
}

export default function Users() {
    const { data: users, isLoading } = useTenantUsers();
    const { data: me } = useAuthUser();
    const setActive = useSetUserActive();
    const resend = useResendInvitation();
    const resetMfa = useResetUserMfa();
    const can = useCan();
    const canManage = can('users.manage');
    const [editing, setEditing] = useState<TenantUser | undefined>();
    const [isInviting, setIsInviting] = useState(false);
    const [toggling, setToggling] = useState<TenantUser | null>(null);
    const [notice, setNotice] = useState<string | null>(null);

    const columns = useMemo(
        () => [
            columnHelper.accessor('name', {
                header: 'Usuario',
                cell: (info) => (
                    <div>
                        <p className="font-medium text-foreground">
                            {info.getValue()}
                            {info.row.original.id === me?.id && <span className="ml-1.5 text-xs text-muted-foreground">(tú)</span>}
                        </p>
                        <p className="text-xs text-muted-foreground">{info.row.original.email}</p>
                    </div>
                ),
            }),
            columnHelper.accessor('roles', {
                header: 'Rol',
                cell: (info) => info.getValue().map((role) => role.label).join(', ') || '—',
            }),
            columnHelper.display({ id: 'status', header: 'Estado', cell: (info) => <UserStatus user={info.row.original} /> }),
            columnHelper.display({ id: 'mfa', header: 'Dos pasos', cell: (info) => <MfaBadge user={info.row.original} /> }),
            columnHelper.display({
                id: 'actions',
                header: () => <span className="sr-only">Acciones</span>,
                meta: { className: 'w-[1%] whitespace-nowrap text-right' },
                cell: (info) => {
                    const user = info.row.original;
                    if (!canManage) return null;
                    const isMe = user.id === me?.id;

                    return (
                        <div className="inline-flex gap-1">
                            {!user.email_verified_at && !user.deactivated_at && (
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            aria-label="Reenviar invitación"
                                            loading={resend.isPending && resend.variables === user.id}
                                            onClick={() =>
                                                resend.mutate(user.id, { onSuccess: () => setNotice(`Invitación reenviada a ${user.email}.`) })
                                            }
                                        >
                                            <MailCheck />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Reenviar invitación</TooltipContent>
                                </Tooltip>
                            )}
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button variant="ghost" size="icon-sm" aria-label="Editar usuario" onClick={() => setEditing(user)}>
                                        <Pencil />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>Editar nombre y rol</TooltipContent>
                            </Tooltip>
                            {!isMe && <ResetMfaAction user={user} reset={resetMfa} />}
                            {!isMe && (
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant={user.deactivated_at ? 'ghostsuccess' : 'ghosterror'}
                                            size="icon-sm"
                                            aria-label={user.deactivated_at ? 'Reactivar usuario' : 'Desactivar usuario'}
                                            onClick={() => setToggling(user)}
                                        >
                                            {user.deactivated_at ? <UserCheck /> : <UserX />}
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>{user.deactivated_at ? 'Reactivar' : 'Desactivar'}</TooltipContent>
                                </Tooltip>
                            )}
                        </div>
                    );
                },
            }),
        ],
        [canManage, me?.id, resend, resetMfa],
    );

    const reactivating = toggling?.deactivated_at !== null && toggling !== null;
    const error = setActive.error ?? resend.error;

    return (
        <div>
            <PageHeader
                title="Usuarios"
                description="Personas con acceso a esta cuenta y su rol."
                actions={
                    canManage && (
                        <Button
                            onClick={() => {
                                setEditing(undefined);
                                setIsInviting(true);
                            }}
                        >
                            <Plus />
                            Invitar usuario
                        </Button>
                    )
                }
            />

            {notice && (
                <Alert variant="success" className="mb-4">
                    <AlertTitle>{notice}</AlertTitle>
                </Alert>
            )}
            {error && (
                <Alert variant="error" className="mb-4">
                    <AlertTitle>{apiErrorMessage(error, ['user', 'role_id'], 'No se pudo completar la acción.')}</AlertTitle>
                </Alert>
            )}

            {isLoading ? (
                <Skeleton className="h-64 w-full rounded-xl" />
            ) : users && users.length > 0 ? (
                <DataTable data={users} columns={columns} minWidth="min-w-[640px]" />
            ) : (
                <EmptyState icon={UsersIcon} title="Aún no hay usuarios" />
            )}

            <UserFormDialog
                open={isInviting || editing !== undefined}
                user={editing}
                onClose={() => {
                    setIsInviting(false);
                    setEditing(undefined);
                }}
            />

            <ConfirmDialog
                open={toggling !== null}
                title={reactivating ? 'Reactivar usuario' : 'Desactivar usuario'}
                description={
                    reactivating
                        ? `${toggling?.name} podrá volver a iniciar sesión con su contraseña.`
                        : `${toggling?.name} no podrá iniciar sesión y se cortará su sesión abierta. Sus datos y su historial se conservan.`
                }
                confirmLabel={reactivating ? 'Reactivar' : 'Desactivar'}
                destructive={!reactivating}
                loading={setActive.isPending}
                onConfirm={() =>
                    toggling &&
                    setActive.mutate(
                        { id: toggling.id, active: reactivating },
                        { onSettled: () => setToggling(null) },
                    )
                }
                onCancel={() => setToggling(null)}
            />
        </div>
    );
}
