import { Loader2 } from 'lucide-react';
import { Navigate, Outlet } from 'react-router-dom';
import { useAuthUser } from '@/hooks/useAuth';

/**
 * Sesión iniciada y con el segundo factor en regla. El backend lo exige
 * igual en cada ruta (middleware `mfa`); esto solo lleva a la pantalla
 * correcta: login si falta el código, enrolamiento si es obligatorio ya.
 */
export default function ProtectedRoute() {
    const { data: user, isLoading } = useAuthUser();

    if (isLoading) {
        return (
            <div className="flex min-h-screen items-center justify-center">
                <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
            </div>
        );
    }

    if (!user || (user.mfa.enabled && !user.mfa.session_verified)) {
        return <Navigate to="/login" replace />;
    }

    if (user.mfa.required_now) {
        return <Navigate to="/mfa/setup" replace />;
    }

    return <Outlet />;
}
