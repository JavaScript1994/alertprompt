import { useCallback } from 'react';
import { useAuthUser } from '@/hooks/useAuth';
import type { PermissionName, User } from '@/types';

export function userCan(user: User | null | undefined, permission: PermissionName): boolean {
    return user?.permissions.includes(permission) ?? false;
}

/**
 * Solo para mostrar u ocultar UI: quien decide de verdad es el backend
 * (middleware `permission:` en routes/api.php).
 */
export function useCan(): (permission: PermissionName) => boolean {
    const { data: user } = useAuthUser();

    return useCallback((permission: PermissionName) => userCan(user, permission), [user]);
}
