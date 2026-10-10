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
                <h2 className="text-[1.75rem] font-bold tracking-tight text-brand-700 shorter:text-2xl dark:text-white">Protege tu cuenta</h2>
                <p className="mt-3 text-muted-foreground shorter:mt-1.5">
                    {user.mfa.required_now
                        ? 'Tu usuario necesita verificación en dos pasos para usar AlertPrompt.'
                        : 'Activa la verificación en dos pasos con tu app de autenticación.'}
                </p>
            </div>
            <div className="mt-8 short:mt-5">
                <MfaEnrollment user={user} onFinished={() => navigate('/', { replace: true })} />
            </div>
            <div className="mt-6 flex justify-center border-t pt-4 short:mt-4 short:pt-2">
                <Button variant="ghost" size="sm" loading={logout.isPending} onClick={() => logout.mutate()}>
                    Cerrar sesión
                </Button>
            </div>
        </div>
    );
}
