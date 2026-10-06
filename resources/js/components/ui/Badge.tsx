export type BadgeVariant = 'success' | 'warning' | 'error' | 'info' | 'neutral' | 'brand';

const variants: Record<BadgeVariant, string> = {
    // WhatsApp Green: color de marca para estados de éxito/verificación.
    success: 'bg-whatsapp-50 text-whatsapp-800 ring-whatsapp-200',
    warning: 'bg-amber-50 text-amber-700 ring-amber-200',
    error: 'bg-red-50 text-red-700 ring-red-200',
    info: 'bg-blue-50 text-blue-700 ring-blue-200',
    neutral: 'bg-slate-100 text-slate-600 ring-slate-200',
    brand: 'bg-brand-50 text-brand-700 ring-brand-200',
};

const dots: Record<BadgeVariant, string> = {
    success: 'bg-whatsapp-500',
    warning: 'bg-amber-500',
    error: 'bg-red-500',
    info: 'bg-blue-500',
    neutral: 'bg-slate-400',
    brand: 'bg-brand-500',
};

export default function Badge({
    children,
    variant = 'neutral',
    dot = false,
    className = '',
}: {
    children: React.ReactNode;
    variant?: BadgeVariant;
    dot?: boolean;
    className?: string;
}) {
    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset ${variants[variant]} ${className}`}
        >
            {dot && <span className={`h-1.5 w-1.5 shrink-0 rounded-full ${dots[variant]}`} />}
            {children}
        </span>
    );
}
