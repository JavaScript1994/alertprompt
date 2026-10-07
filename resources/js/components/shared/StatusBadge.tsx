import { Badge, type BadgeVariant } from '@/components/ui/badge';
import type { CampaignStatus, TemplateStatus } from '@/types';

const CAMPAIGN_STATUS: Record<CampaignStatus, { label: string; variant: BadgeVariant }> = {
    draft: { label: 'Borrador', variant: 'neutral' },
    scheduled: { label: 'Programada', variant: 'info' },
    running: { label: 'En curso', variant: 'success' },
    paused: { label: 'Pausada', variant: 'warning' },
    completed: { label: 'Completada', variant: 'primary' },
    cancelled: { label: 'Cancelada', variant: 'error' },
};

const TEMPLATE_STATUS: Record<TemplateStatus, { label: string; variant: BadgeVariant }> = {
    draft: { label: 'Borrador', variant: 'neutral' },
    pending_approval: { label: 'Pendiente', variant: 'warning' },
    approved: { label: 'Aprobada', variant: 'success' },
    rejected: { label: 'Rechazada', variant: 'error' },
    disabled: { label: 'Deshabilitada', variant: 'neutral' },
};

export const campaignStatusLabel = (status: CampaignStatus): string => CAMPAIGN_STATUS[status].label;
export const templateStatusLabel = (status: TemplateStatus): string => TEMPLATE_STATUS[status].label;

export function CampaignStatusBadge({ status }: { status: CampaignStatus }) {
    const { label, variant } = CAMPAIGN_STATUS[status];
    return <Badge variant={variant}>{label}</Badge>;
}

export function TemplateStatusBadge({ status }: { status: TemplateStatus }) {
    const { label, variant } = TEMPLATE_STATUS[status];
    return <Badge variant={variant}>{label}</Badge>;
}
