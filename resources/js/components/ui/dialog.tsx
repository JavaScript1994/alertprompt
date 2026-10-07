import * as DialogPrimitive from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

const Dialog = DialogPrimitive.Root;
const DialogTrigger = DialogPrimitive.Trigger;
const DialogClose = DialogPrimitive.Close;

function DialogOverlay({ className, ...props }: ComponentProps<typeof DialogPrimitive.Overlay>) {
    return (
        <DialogPrimitive.Overlay
            data-slot="dialog-overlay"
            className={cn(
                'fixed inset-0 z-50 bg-black/60 backdrop-blur-[2px] data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=closed]:animate-out data-[state=closed]:fade-out-0',
                className,
            )}
            {...props}
        />
    );
}

function DialogContent({ className, children, ...props }: ComponentProps<typeof DialogPrimitive.Content>) {
    return (
        <DialogPrimitive.Portal>
            <DialogOverlay />
            <DialogPrimitive.Content
                data-slot="dialog-content"
                className={cn(
                    'fixed top-1/2 left-1/2 z-50 flex max-h-[90vh] w-[calc(100%-2rem)] max-w-lg -translate-x-1/2 -translate-y-1/2 flex-col gap-4 overflow-y-auto rounded-lg bg-card p-6 text-card-foreground shadow-lg duration-200',
                    'data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95',
                    className,
                )}
                {...props}
            >
                {children}
                <DialogPrimitive.Close className="absolute top-3 right-3 rounded-full p-1 text-muted-foreground transition-colors hover:bg-lightprimary hover:text-primary">
                    <X className="size-5" />
                    <span className="sr-only">Cerrar</span>
                </DialogPrimitive.Close>
            </DialogPrimitive.Content>
        </DialogPrimitive.Portal>
    );
}

function DialogHeader({ className, ...props }: ComponentProps<'div'>) {
    return <div data-slot="dialog-header" className={cn('flex flex-col gap-1.5 pr-6', className)} {...props} />;
}

function DialogFooter({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            data-slot="dialog-footer"
            className={cn('flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end', className)}
            {...props}
        />
    );
}

function DialogTitle({ className, ...props }: ComponentProps<typeof DialogPrimitive.Title>) {
    return (
        <DialogPrimitive.Title
            data-slot="dialog-title"
            className={cn('text-lg leading-none font-semibold', className)}
            {...props}
        />
    );
}

function DialogDescription({ className, ...props }: ComponentProps<typeof DialogPrimitive.Description>) {
    return (
        <DialogPrimitive.Description
            data-slot="dialog-description"
            className={cn('text-sm text-muted-foreground', className)}
            {...props}
        />
    );
}

export { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger };
