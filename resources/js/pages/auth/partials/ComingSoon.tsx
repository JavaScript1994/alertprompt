import type { ReactNode } from 'react';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

/**
 * Enlace del diseño cuya funcionalidad todavía no existe (recuperar contraseña,
 * registro de empresas, páginas legales). Se muestra para respetar el diseño,
 * pero no navega: avisa "Próximamente" en vez de llevar a una página vacía.
 */
export default function ComingSoon({ children, className }: { children: ReactNode; className?: string }) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <button type="button" aria-disabled="true" className={cn('cursor-help', className)}>
                    {children}
                </button>
            </TooltipTrigger>
            <TooltipContent>Próximamente</TooltipContent>
        </Tooltip>
    );
}
