import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { Label } from './label';

/**
 * Envoltorio de formulario: label + control + error o ayuda. Los controles
 * shadcn son "tontos" (no traen label ni error), así que este componente
 * reemplaza lo que antes vivía dentro de Input/Select/Textarea.
 */
function Field({
    label,
    htmlFor,
    error,
    hint,
    className,
    children,
}: {
    label?: ReactNode;
    htmlFor?: string;
    error?: string | null;
    hint?: ReactNode;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div data-slot="field" className={cn('space-y-2', className)}>
            {label && <Label htmlFor={htmlFor}>{label}</Label>}
            {children}
            {error ? (
                <p className="text-sm text-error">{error}</p>
            ) : (
                hint && <p className="text-xs text-muted-foreground">{hint}</p>
            )}
        </div>
    );
}

export { Field };
