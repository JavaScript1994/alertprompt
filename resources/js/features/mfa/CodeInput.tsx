import type { ComponentProps } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/** Campo de 6 dígitos: teclado numérico y autocompletado de códigos de un solo uso. */
export default function CodeInput({ className, ...props }: ComponentProps<'input'>) {
    return (
        <Input
            inputMode="numeric"
            autoComplete="one-time-code"
            maxLength={6}
            placeholder="000000"
            className={cn('h-12 text-center font-mono text-xl tracking-[0.5em]', className)}
            {...props}
        />
    );
}
