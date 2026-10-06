import { Mail, MessageCircle, Smartphone } from 'lucide-react';
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
    whatsapp: { icon: MessageCircle, classes: 'bg-whatsapp-500/10 text-whatsapp-700' },
    sms: { icon: Smartphone, classes: 'bg-brand-500/10 text-brand-700' },
    email: { icon: Mail, classes: 'bg-prompt-500/10 text-prompt-700' },
};

export default function ChannelBadge({ channel }: { channel: TemplateChannel }) {
    const { icon: Icon, classes } = CHANNEL_STYLES[channel];

    return (
        <span
            className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${classes}`}
        >
            <Icon className="h-3 w-3" strokeWidth={2.25} />
            {CHANNEL_LABELS[channel]}
        </span>
    );
}
