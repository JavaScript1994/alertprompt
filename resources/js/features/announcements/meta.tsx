import { AlertTriangle, Bell, Info, Sparkles, Tag, type LucideIcon } from 'lucide-react';
import type { BadgeVariant } from '@/components/ui/badge';
import type { AnnouncementType } from './types';

interface TypeMeta {
    label: string;
    /** Para quien escribe el comunicado: dónde y cómo se muestra. */
    description: string;
    icon: LucideIcon;
    badge: BadgeVariant;
    /** Clases del ícono en listas y banners. */
    tone: string;
    canEmail: boolean;
}

export const ANNOUNCEMENT_TYPES: Record<AnnouncementType, TypeMeta> = {
    feature: {
        label: 'Novedad',
        description: 'Nuevas funciones. Ventana de novedades al entrar (una vez) y campana.',
        icon: Sparkles,
        badge: 'primary',
        tone: 'bg-lightprimary text-primary dark:text-brand-200',
        canEmail: false,
    },
    notice: {
        label: 'Aviso',
        description: 'Información general, como un mantenimiento. Solo campana.',
        icon: Info,
        badge: 'info',
        tone: 'bg-lightinfo text-info',
        canEmail: false,
    },
    incident: {
        label: 'Incidente',
        description: 'Algo no funciona. Banner rojo fijo hasta que lo marques resuelto.',
        icon: AlertTriangle,
        badge: 'error',
        tone: 'bg-lighterror text-error',
        canEmail: true,
    },
    action_required: {
        label: 'Acción requerida',
        description: 'El cliente debe hacer algo. Banner hasta que lo cierre.',
        icon: Bell,
        badge: 'warning',
        tone: 'bg-lightwarning text-warning',
        canEmail: true,
    },
    promotion: {
        label: 'Promoción',
        description: 'Servicios o planes que puede contratar. Solo campana, nunca por correo.',
        icon: Tag,
        badge: 'success',
        tone: 'bg-lightsuccess text-success',
        canEmail: false,
    },
};

export const TYPE_ORDER: AnnouncementType[] = ['feature', 'notice', 'incident', 'action_required', 'promotion'];

/** "hace 5 min", "hace 2 días", o la fecha. */
export function timeAgo(iso: string): string {
    const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
    if (minutes < 1) return 'ahora';
    if (minutes < 60) return `hace ${minutes} min`;
    const hours = Math.round(minutes / 60);
    if (hours < 24) return `hace ${hours} h`;
    const days = Math.round(hours / 24);
    if (days < 7) return `hace ${days} ${days === 1 ? 'día' : 'días'}`;
    return new Date(iso).toLocaleDateString('es-PE', { day: 'numeric', month: 'short', year: 'numeric' });
}
