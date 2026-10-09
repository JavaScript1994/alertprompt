import { Badge, type BadgeVariant } from '@/components/ui/badge';
import type { ChannelAccountStatus, QualityRating } from '@/types';

const STATUS: Record<ChannelAccountStatus, { label: string; variant: BadgeVariant }> = {
    pending: { label: 'Pendiente de activación', variant: 'warning' },
    active: { label: 'Activa', variant: 'success' },
    disabled: { label: 'Desactivada', variant: 'neutral' },
};

const QUALITY: Record<QualityRating, { label: string; variant: BadgeVariant }> = {
    GREEN: { label: 'Calidad alta', variant: 'success' },
    YELLOW: { label: 'Calidad media', variant: 'warning' },
    RED: { label: 'Calidad baja', variant: 'error' },
    UNKNOWN: { label: 'Calidad sin datos', variant: 'neutral' },
};

export function ChannelAccountStatusBadge({ status }: { status: ChannelAccountStatus }) {
    return <Badge variant={STATUS[status].variant}>{STATUS[status].label}</Badge>;
}

export function QualityBadge({ rating }: { rating: QualityRating | null }) {
    if (!rating) return null;
    return <Badge variant={QUALITY[rating].variant}>{QUALITY[rating].label}</Badge>;
}

export const ACCOUNT_CHANNEL_LABELS = { whatsapp: 'WhatsApp', sms: 'SMS' } as const;
