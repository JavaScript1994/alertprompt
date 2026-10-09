import * as CheckboxPrimitive from '@radix-ui/react-checkbox';
import { CheckIcon, MinusIcon } from 'lucide-react';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

function Checkbox({ className, ...props }: ComponentProps<typeof CheckboxPrimitive.Root>) {
    return (
        <CheckboxPrimitive.Root
            data-slot="checkbox"
            className={cn(
                'peer size-4 shrink-0 rounded-[4px] border border-input transition-shadow outline-none focus-visible:ring-[3px] focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:border-primary data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[state=indeterminate]:border-primary data-[state=indeterminate]:bg-primary data-[state=indeterminate]:text-primary-foreground',
                className,
            )}
            {...props}
        >
            <CheckboxPrimitive.Indicator className="group flex items-center justify-center text-current">
                {/* checked="indeterminate" (árbol de permisos): guion en vez de check. */}
                <CheckIcon className="size-3.5 group-data-[state=indeterminate]:hidden" />
                <MinusIcon className="hidden size-3.5 group-data-[state=indeterminate]:block" />
            </CheckboxPrimitive.Indicator>
        </CheckboxPrimitive.Root>
    );
}

export { Checkbox };
