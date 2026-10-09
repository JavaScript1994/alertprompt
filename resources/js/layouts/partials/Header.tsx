import { Menu } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useLocation } from 'react-router-dom';
import Logo from '@/components/shared/Logo';
import { Button } from '@/components/ui/button';
import { useAuthUser } from '@/hooks/useAuth';
import { cn } from '@/lib/utils';
import { navigationFor, routeLabel } from './navigation';
import ProfileMenu from './ProfileMenu';
import ThemeToggle from './ThemeToggle';

/** Header sticky de Tailwindadmin: transparente arriba, con fondo y sombra al hacer scroll. */
export default function Header({ onOpenSidebar }: { onOpenSidebar: () => void }) {
    const { pathname } = useLocation();
    const { data: user } = useAuthUser();
    const [isScrolled, setIsScrolled] = useState(false);

    useEffect(() => {
        const onScroll = () => setIsScrolled(window.scrollY > 20);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    return (
        <header
            className={cn(
                'sticky top-0 z-30 transition-[background-color,box-shadow]',
                isScrolled ? 'bg-card shadow-md' : 'bg-transparent',
            )}
        >
            <nav className="flex h-[70px] items-center justify-between gap-3 px-4 sm:px-6">
                <div className="flex min-w-0 items-center gap-2">
                    <Button
                        variant="ghost"
                        size="icon"
                        className="rounded-full lg:hidden"
                        onClick={onOpenSidebar}
                        aria-label="Abrir menú"
                    >
                        <Menu className="size-5" />
                    </Button>
                    <Logo className="lg:hidden" />
                    <p className="hidden truncate text-sm text-muted-foreground lg:block">
                        AlertPrompt <span className="mx-1.5">/</span>
                        <span className="font-medium text-foreground">{routeLabel(pathname, navigationFor(user))}</span>
                    </p>
                </div>

                <div className="flex shrink-0 items-center gap-1 sm:gap-2">
                    <ThemeToggle />
                    <ProfileMenu />
                </div>
            </nav>
        </header>
    );
}
