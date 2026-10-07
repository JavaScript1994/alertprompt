import { CircleHelp } from 'lucide-react';
import { Outlet } from 'react-router-dom';
import Logo from '@/components/shared/Logo';
import ComingSoon from '@/pages/auth/partials/ComingSoon';
import LoginHero from '@/pages/auth/partials/LoginHero';

const CURRENT_YEAR = new Date().getFullYear();

/**
 * Layout de las pantallas públicas: panel de marca a la izquierda y formulario
 * a la derecha. Usa el branding por defecto de AlertPrompt (ver
 * --tenant-accent en app.css para la futura personalización por empresa).
 */
export default function AuthLayout() {
    return (
        <div className="flex min-h-dvh flex-col bg-card xl:h-dvh xl:min-h-[600px]">
            <header className="mx-auto w-full max-w-[1600px] flex shrink-0 items-center justify-between px-6 py-5 sm:px-10 xl:px-16 xl:py-7 short:xl:py-4">
                <Logo size="lg" />
                <ComingSoon className="flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground">
                    <CircleHelp className="size-4.5" />
                    <span className="hidden sm:inline">Centro de ayuda</span>
                </ComingSoon>
            </header>

            <main className="mx-auto w-full max-w-[1600px] grid min-h-0 flex-1 grid-cols-1 gap-16 px-6 sm:px-10 xl:grid-cols-2 xl:px-16 2xl:gap-24">
                <div className="hidden min-h-0 xl:block">
                    <LoginHero />
                </div>
                <div className="flex items-center justify-center py-6 sm:py-10 xl:py-0">
                    <div className="w-full max-w-md">
                        <Outlet />
                    </div>
                </div>
            </main>

            <footer className="mx-auto w-full max-w-[1600px] flex shrink-0 flex-col items-center justify-between gap-3 px-6 py-5 text-xs text-muted-foreground sm:flex-row sm:px-10 xl:px-16 xl:py-6 short:xl:py-4">
                <p>© {CURRENT_YEAR} AlertPrompt. Todos los derechos reservados.</p>
                <div className="flex gap-6">
                    <ComingSoon className="hover:text-foreground">Privacidad</ComingSoon>
                    <ComingSoon className="hover:text-foreground">Términos de uso</ComingSoon>
                </div>
            </footer>
        </div>
    );
}
