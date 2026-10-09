import { Building2, ShieldCheck } from 'lucide-react';
import { NavLink } from 'react-router-dom';
import Logo from '@/components/shared/Logo';
import { useAuthUser } from '@/hooks/useAuth';
import { cn } from '@/lib/utils';
import { navigationFor } from './navigation';

/** Sidebar vertical de Tailwindadmin: secciones con encabezado y item activo sólido. */
export default function Sidebar({ onNavigate }: { onNavigate?: () => void }) {
    const { data: user } = useAuthUser();
    const sections = navigationFor(user);
    // En modo soporte la tarjeta muestra el cliente que se está atendiendo.
    const tenant = user?.impersonating ?? user?.tenant;
    const isPlatform = tenant?.is_platform ?? false;

    return (
        <div className="flex h-full flex-col">
            <div className="flex h-[70px] shrink-0 items-center px-6">
                <Logo />
            </div>

            <nav className="flex-1 overflow-y-auto px-4 pb-4">
                {sections.map((section) => (
                    <div key={section.heading} className="mt-4 first:mt-2">
                        <p className="mb-1 px-3 text-xs leading-[21px] font-bold text-sidebar-foreground/70 uppercase">
                            {section.heading}
                        </p>
                        <ul className="space-y-0.5">
                            {section.items.map((item) => (
                                <li key={item.to}>
                                    {item.comingSoon ? (
                                        <span
                                            aria-disabled="true"
                                            title="Próximamente"
                                            className="flex cursor-not-allowed items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium text-sidebar-foreground/50"
                                        >
                                            <item.icon className="size-5" strokeWidth={1.75} />
                                            <span className="truncate">{item.label}</span>
                                            <span className="ml-auto shrink-0 rounded-full bg-muted px-1.5 py-0.5 text-[9px] font-semibold tracking-wide text-muted-foreground uppercase">
                                                Pronto
                                            </span>
                                        </span>
                                    ) : (
                                        <NavLink
                                            to={item.to}
                                            end={item.to === '/'}
                                            onClick={onNavigate}
                                            className={({ isActive }) =>
                                                cn(
                                                    'flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition-colors',
                                                    isActive
                                                        ? 'bg-primary text-primary-foreground shadow-sm'
                                                        : 'text-sidebar-foreground hover:bg-lightprimary hover:text-primary dark:hover:text-brand-200',
                                                )
                                            }
                                        >
                                            <item.icon className="size-5" strokeWidth={1.75} />
                                            <span className="truncate">{item.label}</span>
                                        </NavLink>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}
            </nav>

            {/* Tarjeta del tenant: ocupa el lugar del bloque promocional de la plantilla. */}
            <div className="shrink-0 p-4">
                <div className="flex items-center gap-3 rounded-lg bg-lightprimary p-4">
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-card text-primary dark:text-brand-200">
                        {isPlatform ? (
                            <ShieldCheck className="size-5" strokeWidth={1.75} />
                        ) : (
                            <Building2 className="size-5" strokeWidth={1.75} />
                        )}
                    </div>
                    <div className="min-w-0">
                        <p className="truncate text-sm font-semibold text-foreground">{tenant?.name ?? '—'}</p>
                        <p className={cn('truncate text-xs text-muted-foreground', !isPlatform && 'capitalize')}>
                            {isPlatform ? 'Plataforma' : `Plan ${tenant?.plan ?? '—'}`}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
