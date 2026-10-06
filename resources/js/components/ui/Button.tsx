import { Loader2 } from 'lucide-react';
import { forwardRef, type ButtonHTMLAttributes } from 'react';

const variants = {
    // Primary CTA: Alert Blue con hover en Prompt Teal (System Mapping UX/UI).
    primary: 'bg-brand-600 text-white shadow-sm hover:bg-prompt-500 focus:ring-brand-500',
    secondary: 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50 focus:ring-brand-500',
    // Secondary CTA: borde Alert Blue con fondo transparente.
    outline: 'bg-transparent text-brand-600 border border-brand-300 hover:bg-brand-50 focus:ring-brand-500',
    ghost: 'bg-transparent text-slate-600 hover:bg-slate-100 focus:ring-slate-300',
    // Success Action: WhatsApp Green — envíos y aprobaciones finales
    // (ej. "Iniciar campaña"). whatsapp-700 en vez del 500 exacto porque el
    // verde base es demasiado claro para pasar contraste AA con texto blanco.
    success: 'bg-whatsapp-700 text-white shadow-sm hover:bg-whatsapp-800 focus:ring-whatsapp-500',
    danger: 'bg-red-600 text-white shadow-sm hover:bg-red-500 focus:ring-red-400',
    'danger-outline': 'bg-transparent text-red-600 border border-red-300 hover:bg-red-50 focus:ring-red-400',
} as const;

const sizes = {
    sm: 'px-3 py-1.5 text-xs rounded-lg gap-1.5',
    md: 'px-4 py-2 text-sm rounded-lg gap-2',
    lg: 'px-5 py-2.5 text-sm rounded-lg gap-2',
} as const;

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: keyof typeof variants;
    size?: keyof typeof sizes;
    loading?: boolean;
}

const Button = forwardRef<HTMLButtonElement, ButtonProps>(
    ({ children, variant = 'primary', size = 'md', loading = false, className = '', disabled, ...props }, ref) => {
        return (
            <button
                ref={ref}
                className={`inline-flex items-center justify-center font-medium transition-colors focus:outline-none focus:ring-4 focus:ring-offset-0 disabled:cursor-not-allowed disabled:opacity-60 ${variants[variant]} ${sizes[size]} ${className}`}
                disabled={loading || disabled}
                {...props}
            >
                {loading && <Loader2 className="h-4 w-4 shrink-0 animate-spin" />}
                {children}
            </button>
        );
    },
);
Button.displayName = 'Button';

export default Button;
