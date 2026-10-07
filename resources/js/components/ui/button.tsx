import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import { Loader2 } from 'lucide-react';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

const buttonVariants = cva(
    'inline-flex shrink-0 items-center justify-center gap-2 rounded-md text-sm font-medium whitespace-nowrap transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*="size-"])]:size-4',
    {
        variants: {
            variant: {
                default: 'bg-primary text-primary-foreground hover:bg-primaryemphasis',
                secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary/90',
                outline: 'border border-border bg-card text-foreground hover:bg-muted',
                outlineprimary: 'border border-primary bg-transparent text-primary hover:bg-primary hover:text-white',
                ghost: 'text-muted-foreground hover:bg-lightprimary hover:text-primary',
                ghosterror: 'text-muted-foreground hover:bg-lighterror hover:text-error',
                ghostsuccess: 'text-muted-foreground hover:bg-lightsuccess hover:text-success',
                // Acción final de envío/aprobación (ej. "Iniciar campaña").
                success: 'bg-success text-white hover:bg-successemphasis',
                destructive: 'bg-error text-white hover:bg-erroremphasis',
                lightprimary: 'bg-lightprimary text-primary hover:bg-primary hover:text-white',
                link: 'h-auto px-0 text-primary underline-offset-4 hover:underline',
            },
            size: {
                default: 'h-10 px-5 py-2',
                sm: 'h-8 px-3 text-xs',
                lg: 'h-11 px-8',
                icon: 'size-9',
                'icon-sm': 'size-8',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

export interface ButtonProps extends ComponentProps<'button'>, VariantProps<typeof buttonVariants> {
    asChild?: boolean;
    /** Muestra un spinner y deshabilita el botón (no aplica con asChild). */
    loading?: boolean;
}

function Button({ className, variant, size, asChild = false, loading = false, disabled, children, ...props }: ButtonProps) {
    if (asChild) {
        return (
            <Slot data-slot="button" className={cn(buttonVariants({ variant, size, className }))} {...props}>
                {children}
            </Slot>
        );
    }

    return (
        <button
            data-slot="button"
            className={cn(buttonVariants({ variant, size, className }))}
            disabled={loading || disabled}
            {...props}
        >
            {loading && <Loader2 className="animate-spin" />}
            {children}
        </button>
    );
}

export { Button, buttonVariants };
