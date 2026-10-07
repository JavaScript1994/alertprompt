import { Mail, MessageCircle, Smartphone } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { TemplateChannel } from '@/types';

const CHANNEL_LABELS: Record<TemplateChannel, string> = {
    whatsapp: 'WhatsApp',
    sms: 'SMS',
    email: 'Email',
};

// Iconografía por canal (System Mapping UX/UI): WhatsApp en su verde
// funcional, SMS en Alert Blue, Email en Prompt Teal para diferenciarlo
// visualmente de SMS dentro de la misma jerarquía "navy".
const CHANNEL_STYLES: Record<TemplateChannel, { icon: typeof MessageCircle; classes: string }> = {
    whatsapp: { icon: MessageCircle, classes: 'bg-whatsapp-500/10 text-whatsapp-700 dark:text-whatsapp-400' },
    sms: { icon: Smartphone, classes: 'bg-brand-500/10 text-brand-700 dark:bg-brand-400/20 dark:text-brand-200' },
    email: { icon: Mail, classes: 'bg-prompt-500/10 text-prompt-700 dark:text-prompt-400' },
};

export default function ChannelBadge({ channel, className }: { channel: TemplateChannel; className?: string }) {
    const { icon: Icon, classes } = CHANNEL_STYLES[channel];

    return (
        <Badge className={cn(classes, className)}>
            <Icon strokeWidth={2.25} />
            {CHANNEL_LABELS[channel]}
        </Badge>
    );
}
