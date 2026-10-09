import { LifeBuoy, LogOut } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { useAuthUser } from '@/hooks/useAuth';
import { useImpersonation } from '@/hooks/useClients';

/** Franja fija mientras el equipo de la plataforma está en el panel de un cliente. */
export default function ImpersonationBanner() {
    const { data: user } = useAuthUser();
    const { stop } = useImpersonation();
    const navigate = useNavigate();

    if (!user?.impersonating) return null;

    const leave = () =>
        stop.mutate(undefined, {
            onSuccess: () => navigate(`/admin/clients/${user.impersonating?.id ?? ''}`),
        });

    return (
        <div className="sticky top-0 z-40 flex flex-wrap items-center justify-center gap-x-4 gap-y-2 bg-warning px-4 py-2 text-sm font-medium text-white">
            <span className="inline-flex items-center gap-2">
                <LifeBuoy className="size-4" />
                Modo soporte: estás en el panel de <strong>{user.impersonating.name}</strong>. Los cambios quedan registrados
                y no se pueden disparar envíos.
            </span>
            <Button size="sm" variant="outline" className="h-7 border-white/60 bg-transparent text-white hover:bg-white/15 hover:text-white" onClick={leave} loading={stop.isPending}>
                <LogOut />
                Salir
            </Button>
        </div>
    );
}
