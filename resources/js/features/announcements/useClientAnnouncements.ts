import { useAuthUser } from '@/hooks/useAuth';
import { useAnnouncements } from './api';

/**
 * Comunicados del usuario del panel de un cliente (o de la plataforma en
 * modo soporte). La administración general no recibe comunicados.
 */
export function useClientAnnouncements() {
    const { data: user } = useAuthUser();
    const enabled = Boolean(user && (!user.tenant.is_platform || user.impersonating));
    const query = useAnnouncements(enabled);

    return { ...query, enabled, items: enabled ? (query.data ?? []) : [] };
}
