import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

export default function EmptyState({
    icon: Icon,
    title,
    description,
    action,
}: {
    icon: LucideIcon;
    title: string;
    description?: string;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed bg-card px-6 py-14 text-center">
            <div className="mb-3 flex size-12 items-center justify-center rounded-full bg-lightprimary">
                <Icon className="size-5 text-primary dark:text-brand-200" strokeWidth={1.75} />
            </div>
            <p className="text-sm font-medium text-foreground">{title}</p>
            {description && <p className="mt-1 max-w-sm text-sm text-muted-foreground">{description}</p>}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}
