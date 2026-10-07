import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

function Input({ className, type = 'text', ...props }: ComponentProps<'input'>) {
    return (
        <input
            type={type}
            data-slot="input"
            className={cn(
                'flex h-10 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-sm text-foreground transition-colors outline-none placeholder:text-muted-foreground',
                'focus-visible:border-primary focus-visible:ring-[3px] focus-visible:ring-ring/40',
                'aria-invalid:border-error aria-invalid:ring-error/15',
                'disabled:cursor-not-allowed disabled:opacity-50',
                'file:mr-4 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-primary',
                className,
            )}
            {...props}
        />
    );
}

export { Input };
