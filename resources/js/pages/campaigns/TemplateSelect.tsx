import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { Template } from '@/types';

const CHANNEL_LABELS: Record<Template['channel'], string> = { whatsapp: 'WhatsApp', sms: 'SMS', email: 'Email' };

/** Select de plantillas aprobadas; 0 = ninguna elegida (lo valida zod). */
export default function TemplateSelect({
    id,
    templates,
    value,
    onChange,
    invalid,
}: {
    id: string;
    templates: Template[] | undefined;
    value: number;
    onChange: (templateId: number) => void;
    invalid?: boolean;
}) {
    return (
        <Select value={value ? String(value) : ''} onValueChange={(next) => onChange(Number(next))}>
            <SelectTrigger id={id} aria-invalid={invalid}>
                <SelectValue placeholder="Elegí una plantilla aprobada…" />
            </SelectTrigger>
            <SelectContent>
                {templates?.map((template) => (
                    <SelectItem key={template.id} value={String(template.id)}>
                        {template.name}
                        <span className="text-muted-foreground"> · {CHANNEL_LABELS[template.channel]}</span>
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
