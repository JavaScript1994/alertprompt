import { BarChart3, CalendarClock, ClipboardCheck, MessagesSquare, Palette, Sparkles, type LucideIcon } from 'lucide-react';

/** Icono por servicio; los creados desde Administración usan Puzzle. */
export const ADD_ON_ICONS: Record<string, LucideIcon> = {
    scheduling: CalendarClock,
    reports: BarChart3,
    branding: Palette,
    'ai-assistant': Sparkles,
    'whatsapp-live-chat': MessagesSquare,
    attendance: ClipboardCheck,
};

export function addOnPrice(price: string | null): string {
    return price === null ? '—' : Number(price).toLocaleString('es-PE', { style: 'currency', currency: 'PEN', maximumFractionDigits: 2 });
}

/** Ruta que abre Configuración → Servicios con la tarjeta del servicio resaltada (la usan las promociones). */
export function addOnLink(key: string): string {
    return `/settings/services?service=${key}`;
}

/** Clave del servicio si `url` es un enlace de addOnLink(). */
export function addOnKeyFromLink(url: string): string | null {
    return /^\/settings\/services\?service=([a-z0-9-]+)$/.exec(url.trim())?.[1] ?? null;
}
