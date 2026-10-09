import { ShieldOff } from 'lucide-react';
import { Outlet } from 'react-router-dom';
import EmptyState from '@/components/shared/EmptyState';
import { useCan } from '@/hooks/usePermissions';
import type { PermissionName } from '@/types';

/** Protege una ruta por permiso. El backend vuelve a validar cada request. */
export default function RequirePermission({ permission }: { permission: PermissionName }) {
    const can = useCan();

    if (!can(permission)) {
        return (
            <EmptyState
                icon={ShieldOff}
                title="No tienes acceso a esta sección"
                description="Pide al administrador de tu cuenta que te asigne un rol con este permiso."
            />
        );
    }

    return <Outlet />;
}
