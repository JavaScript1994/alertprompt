import { forwardRef, useId, type ReactNode, type SelectHTMLAttributes } from 'react';

export interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
    label?: ReactNode;
    error?: string;
    hint?: string;
    children: ReactNode;
}

const Select = forwardRef<HTMLSelectElement, SelectProps>(
    ({ label, error, hint, className = '', id: externalId, children, ...props }, ref) => {
        const generatedId = useId();
        const inputId = externalId ?? generatedId;

        const borderClass = error
            ? 'border-red-300 focus:border-red-500 focus:ring-red-500/10'
            : 'border-slate-300 focus:border-brand-500 focus:ring-brand-500/10';

        return (
            <div>
                {label && (
                    <label htmlFor={inputId} className="mb-1.5 block text-sm font-medium text-slate-700">
                        {label}
                    </label>
                )}
                <select
                    ref={ref}
                    id={inputId}
                    className={`block w-full rounded-lg border bg-white px-3 py-2 text-sm text-ink-900 transition-colors focus:ring-4 focus:outline-none disabled:cursor-not-allowed disabled:bg-slate-50 disabled:opacity-60 ${borderClass} ${className}`}
                    {...props}
                >
                    {children}
                </select>
                {error && <p className="mt-1.5 text-sm text-red-600">{error}</p>}
                {hint && !error && <p className="mt-1.5 text-xs text-slate-400">{hint}</p>}
            </div>
        );
    },
);
Select.displayName = 'Select';

export default Select;
