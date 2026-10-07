import { Building2 } from 'lucide-react';
import { NavLink } from 'react-router-dom';
import Logo from '@/components/shared/Logo';
import { useAuthUser } from '@/hooks/useAuth';
import { cn } from '@/lib/utils';
import { NAV_SECTIONS } from './navigation';

/** Sidebar vertical de Tailwindadmin: secciones con encabezado y item activo sólido. */
export default function Sidebar({ onNavigate }: { onNavigate?: () => void }) {
    const { data: user } = useAuthUser();

    return (
        <div className="flex h-full flex-col">
            <div className="flex h-[70px] shrink-0 items-center px-6">
                <Logo />
            </div>

            <nav className="flex-1 overflow-y-auto px-4 pb-4">
                {NAV_SECTIONS.map((section) => (
                    <div key={section.heading} className="mt-4 first:mt-2">
                        <p className="mb-1 px-3 text-xs leading-[21px] font-bold text-sidebar-foreground/70 uppercase">
                            {section.heading}
                        </p>
                        <ul className="space-y-0.5">
                            {section.items.map((item) => (
                                <li key={item.to}>
                                    <NavLink
                                        to={item.to}
                                        end
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
                        <Building2 className="size-5" strokeWidth={1.75} />
                    </div>
                    <div className="min-w-0">
                        <p className="truncate text-sm font-semibold text-foreground">{user?.tenant.name ?? '—'}</p>
                        <p className="truncate text-xs text-muted-foreground capitalize">
                            Plan {user?.tenant.plan ?? '—'}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
