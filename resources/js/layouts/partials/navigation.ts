import { LayoutDashboard, Megaphone, MessageSquareText, Users, type LucideIcon } from 'lucide-react';

export interface NavItem {
    to: string;
    label: string;
    icon: LucideIcon;
}

export interface NavSection {
    heading: string;
    items: NavItem[];
}

export const NAV_SECTIONS: NavSection[] = [
    {
        heading: 'Inicio',
        items: [{ to: '/', label: 'Dashboard', icon: LayoutDashboard }],
    },
    {
        heading: 'Mensajería',
        items: [
            { to: '/contacts', label: 'Contactos', icon: Users },
            { to: '/templates', label: 'Plantillas', icon: MessageSquareText },
            { to: '/campaigns', label: 'Campañas', icon: Megaphone },
        ],
    },
];

export function routeLabel(pathname: string): string {
    for (const section of NAV_SECTIONS) {
        const item = section.items.find((i) => i.to === pathname);
        if (item) return item.label;
    }
    return 'Panel';
}
