import { ShieldAlert } from 'lucide-react';
import { Link, useLocation } from 'react-router-dom';
import { formatDate } from '@/components/shared/MembershipBits';
import { useAuthUser } from '@/hooks/useAuth';

/** Aviso de plazo de gracia, de códigos por agotarse o de dispositivo por reemplazar. */
export default function MfaBanner() {
    const { data: user } = useAuthUser();
    const { pathname } = useLocation();

    if (!user || pathname === '/settings/security') return null;
    const { mfa } = user;

    const message = !mfa.enabled && mfa.grace_ends_at
        ? `Configura la verificación en dos pasos antes del ${formatDate(mfa.grace_ends_at.slice(0, 10))}; desde ese día será obligatoria.`
        : mfa.can_reenroll
          ? 'Entraste con un método de respaldo. Configura tu nuevo dispositivo de autenticación.'
          : mfa.enabled && mfa.should_regenerate_codes
            ? `Te quedan ${mfa.recovery_codes_remaining} códigos de recuperación. Genera códigos nuevos.`
            : null;

    if (!message) return null;

    return (
        <div className="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 border-b bg-lightwarning px-4 py-2 text-sm text-foreground">
            <span className="inline-flex items-center gap-2">
                <ShieldAlert className="size-4 text-warning" />
                {message}
            </span>
            <Link to="/settings/security" className="font-semibold text-primary hover:underline dark:text-brand-200">
                Ir a Seguridad
            </Link>
        </div>
    );
}
