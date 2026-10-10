import {
    BarChart3,
    Bell,
    Blocks,
    Building2,
    CreditCard,
    FileText,
    Inbox,
    LayoutDashboard,
    Layers,
    Megaphone,
    MessageCircle,
    MessageSquareText,
    Receipt,
    Settings2,
    ShieldCheck,
    Upload,
    User as UserIcon,
    UserCog,
    Users,
    type LucideIcon,
} from 'lucide-react';
import { userCan } from '@/hooks/usePermissions';
import type { ModuleKey, PermissionName, User } from '@/types';

export interface NavItem {
    to: string;
    label: string;
    icon: LucideIcon;
    /** Si el usuario no lo tiene, el item no se muestra. */
    permission: PermissionName;
    /** Pantalla aún no construida: se muestra deshabilitada con "Pronto". */
    comingSoon?: boolean;
    /** Activo solo en su ruta exacta (cuando otro item cuelga de ella). */
    exact?: boolean;
    /** Módulo contratado que exige (config/modules.php). */
    module?: ModuleKey;
}

export interface NavSection {
    heading: string;
    items: NavItem[];
}

const MESSAGING: NavSection = {
    heading: 'Mensajería',
    items: [
        { to: '/contacts', label: 'Contactos', icon: Users, permission: 'contacts.view' },
        { to: '/templates', label: 'Plantillas', icon: MessageSquareText, permission: 'templates.view' },
        { to: '/campaigns', label: 'Campañas', icon: Megaphone, permission: 'campaigns.view' },
    ],
};

/**
 * Administración general (tenant AlertPrompt). Sin mensajería propia: a los
 * datos de un cliente se llega por supervisión o modo soporte.
 */
const PLATFORM_NAV: NavSection[] = [
    {
        heading: 'Inicio',
        items: [{ to: '/', label: 'Dashboard', icon: LayoutDashboard, permission: 'admin.dashboard.view' }],
    },
    {
        heading: 'Clientes',
        items: [
            { to: '/admin/clients/companies', label: 'Empresas', icon: Building2, permission: 'admin.clients.view' },
            { to: '/admin/clients/individuals', label: 'Naturales', icon: UserIcon, permission: 'admin.clients.view' },
        ],
    },
    {
        heading: 'Facturación',
        items: [
            { to: '/admin/plans', label: 'Planes', icon: Layers, permission: 'admin.memberships.view' },
            { to: '/admin/plan-changes', label: 'Solicitudes de plan', icon: Inbox, permission: 'admin.memberships.view' },
            { to: '/admin/invoices', label: 'Comprobantes', icon: Receipt, permission: 'admin.billing.view' },
        ],
    },
    {
        heading: 'Configuración',
        items: [
            { to: '/settings/users', label: 'Equipo', icon: UserCog, permission: 'users.view' },
            { to: '/admin/roles', label: 'Roles y permisos', icon: ShieldCheck, permission: 'admin.roles.view' },
            { to: '/admin/modules', label: 'Módulos', icon: Blocks, permission: 'admin.modules.view' },
            { to: '/admin/alerts', label: 'Alertas', icon: Bell, permission: 'admin.alerts.view' },
            { to: '/admin/bulk-imports', label: 'Cargas masivas', icon: Upload, permission: 'admin.bulk_imports.view' },
        ],
    },
    {
        heading: 'Reportes',
        items: [{ to: '/admin/reports', label: 'Reportes', icon: BarChart3, permission: 'admin.reports.view' }],
    },
];

/** Panel de una empresa o persona natural. */
const CLIENT_NAV: NavSection[] = [
    {
        heading: 'Inicio',
        items: [{ to: '/', label: 'Dashboard', icon: LayoutDashboard, permission: 'dashboard.view' }],
    },
    MESSAGING,
    {
        heading: 'Reportes',
        items: [{ to: '/reports', label: 'Reportes', icon: BarChart3, permission: 'reports.view', module: 'reports' }],
    },
    {
        heading: 'Facturación',
        items: [
            { to: '/billing/payments', label: 'Pagos', icon: Receipt, permission: 'billing.view' },
            { to: '/billing/payment-methods', label: 'Métodos de pago', icon: CreditCard, permission: 'payment_methods.view' },
        ],
    },
    {
        heading: 'Configuración',
        items: [
            { to: '/settings', label: 'Panel', icon: Settings2, permission: 'settings.view', exact: true },
            { to: '/settings/users', label: 'Usuarios', icon: UserCog, permission: 'users.view' },
            { to: '/settings/membership', label: 'Membresía', icon: FileText, permission: 'membership.view' },
            { to: '/settings/whatsapp', label: 'Cuenta de WhatsApp', icon: MessageCircle, permission: 'whatsapp_account.view' },
        ],
    },
];

/** Menú según el tipo de tenant, filtrado por los permisos del usuario. */
export function navigationFor(user: User | null | undefined): NavSection[] {
    if (!user) return [];

    // En modo soporte se ve el panel del cliente, no el de la plataforma.
    const sections = user.tenant.is_platform && !user.impersonating ? PLATFORM_NAV : CLIENT_NAV;

    const modules = (user.impersonating ?? user.tenant).modules;

    return sections
        .map((section) => ({
            ...section,
            items: section.items.filter(
                (item) => userCan(user, item.permission) && (!item.module || modules.includes(item.module)),
            ),
        }))
        .filter((section) => section.items.length > 0);
}

/** Coincide también con subrutas: /admin/roles/5 → "Roles y permisos". */
export function isNavItemActive(item: NavItem, pathname: string): boolean {
    if (item.to === '/' || item.exact) return pathname === item.to;
    return pathname === item.to || pathname.startsWith(`${item.to}/`);
}

/** Páginas de detalle que no son un item del menú. */
const DETAIL_LABELS: [prefix: string, label: string][] = [
    ['/admin/clients/', 'Clientes'],
    ['/billing/invoices/', 'Comprobante'],
];

export function routeLabel(pathname: string, sections: NavSection[]): string {
    for (const section of sections) {
        const item = section.items.find((i) => isNavItemActive(i, pathname));
        if (item) return item.label;
    }
    return DETAIL_LABELS.find(([prefix]) => pathname.startsWith(prefix))?.[1] ?? 'Panel';
}
