import { AlertOctagon, Info, TriangleAlert } from 'lucide-react';
import { Badge, type BadgeVariant } from '@/components/ui/badge';
import type { AlertSeverity } from '@/types';

const SEVERITY: Record<AlertSeverity, { label: string; variant: BadgeVariant; icon: typeof Info }> = {
    critical: { label: 'Crítica', variant: 'error', icon: AlertOctagon },
    warning: { label: 'Advertencia', variant: 'warning', icon: TriangleAlert },
    info: { label: 'Aviso', variant: 'info', icon: Info },
};

/** Estado siempre con ícono + texto, nunca solo color. */
export default function AlertSeverityBadge({ severity }: { severity: AlertSeverity }) {
    const { label, variant, icon: Icon } = SEVERITY[severity];
    return (
        <Badge variant={variant}>
            <Icon />
            {label}
        </Badge>
    );
}
