import type { LucideIcon } from 'lucide-react';
import { forwardRef, useId, type InputHTMLAttributes, type ReactNode } from 'react';

export interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
    label?: ReactNode;
    error?: string;
    hint?: string;
    icon?: LucideIcon;
    rightSlot?: ReactNode;
}

const Input = forwardRef<HTMLInputElement, InputProps>(
    ({ label, error, hint, icon: Icon, rightSlot, className = '', id: externalId, ...props }, ref) => {
        const generatedId = useId();
        const inputId = externalId ?? generatedId;

        const borderClass = error
            ? 'border-red-300 focus:border-red-500 focus:ring-red-500/10 bg-red-50/30'
            : 'border-slate-300 focus:border-brand-500 focus:ring-brand-500/10';

        return (
            <div>
                {label && (
                    <label htmlFor={inputId} className="mb-1.5 block text-sm font-medium text-slate-700">
                        {label}
                    </label>
                )}
                <div className="relative">
                    {Icon && (
                        <Icon className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    )}
                    <input
                        ref={ref}
                        id={inputId}
                        className={`block w-full rounded-lg border py-2 text-sm text-ink-900 placeholder:text-slate-400 transition-colors focus:ring-4 focus:outline-none disabled:cursor-not-allowed disabled:bg-slate-50 disabled:opacity-60 ${Icon ? 'pl-9' : 'pl-3'} ${rightSlot ? 'pr-10' : 'pr-3'} ${borderClass} ${className}`}
                        {...props}
                    />
                    {rightSlot && <span className="absolute top-1/2 right-3 -translate-y-1/2">{rightSlot}</span>}
                </div>
                {error && <p className="mt-1.5 text-sm text-red-600">{error}</p>}
                {hint && !error && <p className="mt-1.5 text-xs text-slate-400">{hint}</p>}
            </div>
        );
    },
);
Input.displayName = 'Input';

export default Input;
