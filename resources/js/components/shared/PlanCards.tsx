import { Check, Mail, MessageCircle, Smartphone } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { Plan, TemplateChannel } from '@/types';

const CHANNELS: { key: TemplateChannel; label: string; icon: typeof Mail }[] = [
    { key: 'whatsapp', label: 'WhatsApp', icon: MessageCircle },
    { key: 'sms', label: 'SMS', icon: Smartphone },
    { key: 'email', label: 'Email', icon: Mail },
];

export function planPrice(plan: Pick<Plan, 'monthly_price'>): string {
    return plan.monthly_price === null
        ? 'A medida'
        : Number(plan.monthly_price).toLocaleString('es-PE', { style: 'currency', currency: 'PEN', maximumFractionDigits: 0 });
}

/** Fila de planes (3 por fila en escritorio); el plan vigente va resaltado. */
export default function PlanCards({
    plans,
    currentKey,
    footer,
}: {
    plans: Plan[];
    currentKey?: string | null;
    /** Contenido al pie de cada tarjeta (p. ej. botón Editar en administración). */
    footer?: (plan: Plan) => ReactNode;
}) {
    return (
        <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
            {plans.map((plan) => {
                const isCurrent = plan.key === currentKey;

                return (
                    <Card
                        key={plan.id}
                        className={cn(
                            'relative gap-5 p-6',
                            isCurrent && 'ring-2 ring-primary dark:ring-brand-300',
                            !plan.is_public && 'border-dashed',
                            !plan.is_active && 'bg-muted/40',
                        )}
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <p className="text-lg font-semibold text-foreground">{plan.name}</p>
                                {plan.description && <p className="mt-1 text-sm text-muted-foreground">{plan.description}</p>}
                            </div>
                            {isCurrent && (
                                <Badge variant="primary">
                                    <Check />
                                    Plan actual
                                </Badge>
                            )}
                            {!plan.is_active && <Badge variant="neutral">Inactivo</Badge>}
                        </div>

                        <p>
                            <span className="text-3xl font-semibold tracking-tight text-foreground tabular-nums">{planPrice(plan)}</span>
                            {plan.monthly_price !== null && <span className="ml-1 text-sm text-muted-foreground">/ mes + IGV</span>}
                        </p>

                        <ul className="space-y-2.5 border-t pt-4 text-sm">
                            {CHANNELS.map(({ key, label, icon: Icon }) => {
                                const quota = plan.quotas[key];
                                return (
                                    <li key={key} className="flex items-center justify-between gap-3">
                                        <span className="inline-flex items-center gap-2 text-muted-foreground">
                                            <Icon className="size-4" />
                                            {label}
                                        </span>
                                        <span className="font-medium text-foreground tabular-nums">
                                            {quota === null ? 'Sin límite' : `${quota.toLocaleString('es-PE')} / mes`}
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>

                        {footer?.(plan)}
                    </Card>
                );
            })}
        </div>
    );
}
