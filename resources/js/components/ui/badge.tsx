import { cva, type VariantProps } from 'class-variance-authority';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

// Variantes "light" de la plantilla: fondo tenue + texto del color del estado.
const badgeVariants = cva(
    'inline-flex w-fit shrink-0 items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap [&_svg]:size-3 [&_svg]:shrink-0',
    {
        variants: {
            variant: {
                primary: 'bg-lightprimary text-primary dark:text-brand-200',
                secondary: 'bg-lightsecondary text-secondary',
                success: 'bg-lightsuccess text-success',
                warning: 'bg-lightwarning text-warning',
                error: 'bg-lighterror text-error',
                info: 'bg-lightinfo text-info',
                neutral: 'bg-muted text-muted-foreground',
                outline: 'border border-border text-foreground',
            },
        },
        defaultVariants: { variant: 'neutral' },
    },
);

export type BadgeVariant = NonNullable<VariantProps<typeof badgeVariants>['variant']>;

function Badge({ className, variant, ...props }: ComponentProps<'span'> & VariantProps<typeof badgeVariants>) {
    return <span data-slot="badge" className={cn(badgeVariants({ variant }), className)} {...props} />;
}

export { Badge, badgeVariants };
