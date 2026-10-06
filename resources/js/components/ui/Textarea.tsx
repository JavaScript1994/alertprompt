import { forwardRef, useId, type ReactNode, type TextareaHTMLAttributes } from 'react';

export interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
    label?: ReactNode;
    error?: string;
    hint?: string;
}

const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(
    ({ label, error, hint, className = '', id: externalId, ...props }, ref) => {
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
                <textarea
                    ref={ref}
                    id={inputId}
                    className={`block w-full rounded-lg border px-3 py-2 text-sm text-ink-900 placeholder:text-slate-400 transition-colors focus:ring-4 focus:outline-none disabled:cursor-not-allowed disabled:bg-slate-50 disabled:opacity-60 ${borderClass} ${className}`}
                    {...props}
                />
                {error && <p className="mt-1.5 text-sm text-red-600">{error}</p>}
                {hint && !error && <p className="mt-1.5 text-xs text-slate-400">{hint}</p>}
            </div>
        );
    },
);
Textarea.displayName = 'Textarea';

export default Textarea;
