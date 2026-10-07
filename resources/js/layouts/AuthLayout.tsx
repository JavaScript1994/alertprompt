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
        <div className="flex min-h-screen flex-col bg-card">
            <header className="flex items-center justify-between px-6 py-6 sm:px-10 lg:px-16 lg:py-8">
                <Logo size="lg" />
                <ComingSoon className="flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground">
                    <CircleHelp className="size-4.5" />
                    <span className="hidden sm:inline">Centro de ayuda</span>
                </ComingSoon>
            </header>

            <main className="grid flex-1 grid-cols-1 gap-10 px-6 sm:px-10 lg:grid-cols-2 lg:px-16 xl:gap-20">
                <div className="hidden min-h-[680px] lg:block">
                    <LoginHero />
                </div>
                <div className="flex items-center justify-center py-8 lg:py-0">
                    <div className="w-full max-w-md">
                        <Outlet />
                    </div>
                </div>
            </main>

            <footer className="flex flex-col items-center justify-between gap-3 px-6 py-6 text-xs text-muted-foreground sm:flex-row sm:px-10 lg:px-16 lg:py-8">
                <p>© {CURRENT_YEAR} AlertPrompt. Todos los derechos reservados.</p>
                <div className="flex gap-6">
                    <ComingSoon className="hover:text-foreground">Privacidad</ComingSoon>
                    <ComingSoon className="hover:text-foreground">Términos de uso</ComingSoon>
                </div>
            </footer>
        </div>
    );
}
