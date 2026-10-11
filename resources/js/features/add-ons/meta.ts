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
