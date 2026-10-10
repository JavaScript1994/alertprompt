import { Navigate, useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import MfaEnrollment from '@/features/mfa/MfaEnrollment';
import { useAuthUser, useLogout } from '@/hooks/useAuth';

/** Enrolamiento obligatorio tras el login (pantalla completa, sin el panel). */
export default function MfaSetupPage() {
    const { data: user, isLoading } = useAuthUser();
    const logout = useLogout();
    const navigate = useNavigate();

    if (isLoading) return null;
    if (!user) return <Navigate to="/login" replace />;
    if (user.mfa.enabled && !user.mfa.session_verified) return <Navigate to="/login" replace />;

    return (
        <div>
            <div className="text-center">
                <h2 className="text-[1.75rem] font-bold tracking-tight text-brand-700 dark:text-white">Protege tu cuenta</h2>
                <p className="mt-3 text-muted-foreground">
                    {user.mfa.required_now
                        ? 'Tu usuario necesita verificación en dos pasos para usar AlertPrompt.'
                        : 'Activa la verificación en dos pasos con tu app de autenticación.'}
                </p>
            </div>
            <div className="mt-8">
                <MfaEnrollment user={user} onFinished={() => navigate('/', { replace: true })} />
            </div>
            <Button variant="ghost" className="mt-4 w-full" loading={logout.isPending} onClick={() => logout.mutate()}>
                Cerrar sesión
            </Button>
        </div>
    );
}
