import { ChevronRight, LayoutDashboard, LogOut, Megaphone, Menu, MessageSquareText, Users, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Link, Outlet, useLocation } from 'react-router-dom';
import { useAuthUser, useLogout } from '@/hooks/useAuth';

const NAV_ITEMS = [
    { to: '/', label: 'Dashboard', icon: LayoutDashboard },
    { to: '/contacts', label: 'Contactos', icon: Users },
    { to: '/templates', label: 'Plantillas', icon: MessageSquareText },
    { to: '/campaigns', label: 'Campañas', icon: Megaphone },
];

const ROUTE_LABELS: Record<string, string> = {
    '/': 'Dashboard',
    '/contacts': 'Contactos',
    '/templates': 'Plantillas',
    '/campaigns': 'Campañas',
};

function initials(name?: string): string {
    if (!name) return '·';
    const parts = name.trim().split(/\s+/);
    return ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase();
}

function Breadcrumbs() {
    const { pathname } = useLocation();
    const label = ROUTE_LABELS[pathname] ?? 'Panel';

    return (
        <div className="flex items-center gap-2 text-sm">
            <span className="text-slate-400">AlertPrompt</span>
            <ChevronRight className="h-3.5 w-3.5 text-slate-300" />
            <span className="font-medium text-slate-700">{label}</span>
        </div>
    );
}

export default function DashboardLayout() {
    const { data: user } = useAuthUser();
    const logout = useLogout();
    const location = useLocation();
    const [isSidebarOpen, setIsSidebarOpen] = useState(false);

    // Cerrar el drawer móvil al navegar entre secciones.
    useEffect(() => {
        setIsSidebarOpen(false);
    }, [location.pathname]);

    const sidebarContent = (
        <>
            <div className="flex items-center gap-2.5 px-5 py-5">
                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white">
                    A
                </div>
                <div className="min-w-0">
                    <p className="text-sm font-semibold text-ink-900">AlertPrompt</p>
                    <p className="truncate text-xs text-slate-500">{user?.tenant.name}</p>
                </div>
                <button
                    onClick={() => setIsSidebarOpen(false)}
                    className="ml-auto rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 lg:hidden"
                >
                    <X className="h-4 w-4" />
                </button>
            </div>

            <nav className="flex-1 space-y-0.5 px-3 py-2">
                {NAV_ITEMS.map((item) => {
                    const isActive = location.pathname === item.to;
                    const Icon = item.icon;

                    return (
                        <Link
                            key={item.to}
                            to={item.to}
                            className={`flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                                isActive
                                    ? 'bg-brand-50 text-brand-700'
                                    : 'text-slate-600 hover:bg-slate-50 hover:text-ink-900'
                            }`}
                        >
                            <Icon className="h-4 w-4" strokeWidth={2} />
                            {item.label}
                        </Link>
                    );
                })}
            </nav>

            <div className="border-t border-slate-200 p-3">
                <div className="flex items-center gap-2.5 rounded-lg px-2 py-2">
                    <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">
                        {initials(user?.name)}
                    </div>
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium text-ink-900">{user?.name}</p>
                        <p className="truncate text-xs text-slate-500">{user?.role}</p>
                    </div>
                    <button
                        onClick={() => logout.mutate()}
                        disabled={logout.isPending}
                        title="Salir"
                        className="shrink-0 rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 disabled:opacity-50"
                    >
                        <LogOut className="h-4 w-4" strokeWidth={2} />
                    </button>
                </div>
            </div>
        </>
    );

    return (
        <div className="flex min-h-screen bg-surface">
            {/* Sidebar fija en desktop: sticky al viewport para que no se desplace con el
                scroll del contenido de la pestaña activa (ej. listados largos en Campañas). */}
            <aside className="hidden w-64 shrink-0 flex-col border-r border-slate-200 bg-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:overflow-y-auto">
                {sidebarContent}
            </aside>

            {/* Drawer móvil */}
            {isSidebarOpen && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <div className="absolute inset-0 bg-slate-900/50" onClick={() => setIsSidebarOpen(false)} />
                    <aside className="relative flex h-full w-64 max-w-[80vw] flex-col bg-white shadow-xl">
                        {sidebarContent}
                    </aside>
                </div>
            )}

            <div className="min-w-0 flex-1">
                <header className="flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3.5 sm:px-6 lg:px-8">
                    <button
                        onClick={() => setIsSidebarOpen(true)}
                        className="shrink-0 rounded-md p-1.5 text-slate-500 hover:bg-slate-100 lg:hidden"
                    >
                        <Menu className="h-5 w-5" />
                    </button>
                    <Breadcrumbs />
                </header>

                <main className="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    <div className="mx-auto max-w-6xl">
                        <Outlet />
                    </div>
                </main>
            </div>
        </div>
    );
}
