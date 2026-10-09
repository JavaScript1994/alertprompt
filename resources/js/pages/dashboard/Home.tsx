import { useAuthUser } from '@/hooks/useAuth';
import AdminDashboard from './AdminDashboard';
import Dashboard from './Dashboard';

/** Inicio según quién entra: administración general o panel de cliente (también en modo soporte). */
export default function Home() {
    const { data: user } = useAuthUser();

    return user?.tenant.is_platform && !user.impersonating ? <AdminDashboard /> : <Dashboard />;
}
