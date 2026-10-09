import { Badge, type BadgeVariant } from '@/components/ui/badge';
import type { ChannelUsage, Membership, MembershipStatus, TemplateChannel } from '@/types';

const STATUS: Record<MembershipStatus, { label: string; variant: BadgeVariant }> = {
    active: { label: 'Vigente', variant: 'success' },
    scheduled: { label: 'Programada', variant: 'info' },
    expired: { label: 'Vencida', variant: 'neutral' },
    cancelled: { label: 'Cancelada', variant: 'error' },
};

const CHANNEL_LABELS: Record<TemplateChannel, string> = { whatsapp: 'WhatsApp', sms: 'SMS', email: 'Email' };

export function MembershipStatusBadge({ status }: { status: MembershipStatus }) {
    return <Badge variant={STATUS[status].variant}>{STATUS[status].label}</Badge>;
}

export function formatPrice(membership: Pick<Membership, 'price' | 'currency' | 'billing_cycle'>): string {
    const amount = Number(membership.price).toLocaleString('es-PE', { style: 'currency', currency: membership.currency });
    return `${amount} ${membership.billing_cycle === 'monthly' ? '/ mes' : '/ año'} + IGV`;
}

export function formatDate(iso: string): string {
    const [year, month, day] = iso.split('-').map(Number);
    return new Date(year, month - 1, day).toLocaleDateString('es-PE', { day: 'numeric', month: 'short', year: 'numeric' });
}

/** Consumo del mes frente a la cuota, por canal (medidor, no gráfico). */
export function UsageMeters({ usage }: { usage: ChannelUsage }) {
    return (
        <div className="space-y-4">
            {(Object.keys(CHANNEL_LABELS) as TemplateChannel[]).map((channel) => {
                const { used, quota } = usage[channel];
                const ratio = quota ? Math.min(used / quota, 1) : 0;
                const tone = ratio >= 1 ? 'bg-error' : ratio >= 0.8 ? 'bg-warning' : 'bg-primary dark:bg-brand-300';

                return (
                    <div key={channel}>
                        <div className="mb-1.5 flex items-baseline justify-between gap-3 text-sm">
                            <span className="font-medium text-foreground">{CHANNEL_LABELS[channel]}</span>
                            <span className="text-muted-foreground tabular-nums">
                                {used.toLocaleString('es-PE')}
                                {quota !== null ? ` de ${quota.toLocaleString('es-PE')}` : ' · sin límite'}
                            </span>
                        </div>
                        {quota !== null && (
                            // Decorativa: el texto de arriba ya dice "X de Y".
                            <div className="h-2 overflow-hidden rounded-full bg-muted" aria-hidden="true">
                                <div className={`h-full rounded-full ${tone}`} style={{ width: `${ratio * 100}%` }} />
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
