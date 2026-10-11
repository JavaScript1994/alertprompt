import { cn } from '@/lib/utils';

export interface TabItem<K extends string> {
    key: K;
    label: string;
}

/** Pestañas simples (subrayado activo) para fichas con varias secciones. */
export default function Tabs<K extends string>({
    tabs,
    active,
    onChange,
}: {
    tabs: TabItem<K>[];
    active: K;
    onChange: (key: K) => void;
}) {
    return (
        <div role="tablist" className="mb-6 flex shrink-0 gap-1 overflow-x-auto border-b">
            {tabs.map((tab) => (
                <button
                    key={tab.key}
                    type="button"
                    role="tab"
                    aria-selected={tab.key === active}
                    onClick={() => onChange(tab.key)}
                    className={cn(
                        '-mb-px shrink-0 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
                        tab.key === active
                            ? 'border-primary text-primary dark:border-brand-200 dark:text-brand-200'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    )}
                >
                    {tab.label}
                </button>
            ))}
        </div>
    );
}
