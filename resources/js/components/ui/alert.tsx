import { cva, type VariantProps } from 'class-variance-authority';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

const alertVariants = cva(
    'relative grid w-full grid-cols-[0_1fr] items-start gap-y-0.5 rounded-lg px-4 py-3 text-sm has-[>svg]:grid-cols-[1rem_1fr] has-[>svg]:gap-x-3 [&>svg]:size-4 [&>svg]:translate-y-0.5',
    {
        variants: {
            variant: {
                info: 'bg-lightinfo text-info',
                success: 'bg-lightsuccess text-success',
                warning: 'bg-lightwarning text-warning',
                error: 'bg-lighterror text-error',
                primary: 'bg-lightprimary text-primary dark:text-brand-200',
            },
        },
        defaultVariants: { variant: 'info' },
    },
);

function Alert({ className, variant, ...props }: ComponentProps<'div'> & VariantProps<typeof alertVariants>) {
    return <div data-slot="alert" role="alert" className={cn(alertVariants({ variant }), className)} {...props} />;
}

function AlertTitle({ className, ...props }: ComponentProps<'div'>) {
    return <div data-slot="alert-title" className={cn('col-start-2 font-medium', className)} {...props} />;
}

function AlertDescription({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            data-slot="alert-description"
            className={cn('col-start-2 leading-relaxed text-foreground/80', className)}
            {...props}
        />
    );
}

export { Alert, AlertDescription, AlertTitle };
