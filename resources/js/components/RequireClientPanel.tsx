import { Navigate, Outlet } from 'react-router-dom';
import { useAuthUser } from '@/hooks/useAuth';

/**
 * Pantallas del panel de cliente. La administración general no tiene panel
 * de mensajería propio: solo las ve en modo soporte. El backend lo exige
 * igual (middleware client-panel).
 */
export default function RequireClientPanel() {
    const { data: user } = useAuthUser();

    if (user?.tenant.is_platform && !user.impersonating) {
        return <Navigate to="/" replace />;
    }

    return <Outlet />;
}
