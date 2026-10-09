import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/** Reemplaza window.confirm() para acciones destructivas o irreversibles. */
export default function ConfirmDialog({
    open,
    title,
    description,
    confirmLabel = 'Confirmar',
    destructive = false,
    loading = false,
    onConfirm,
    onCancel,
    children,
}: {
    open: boolean;
    title: string;
    description: ReactNode;
    confirmLabel?: string;
    destructive?: boolean;
    loading?: boolean;
    onConfirm: () => void;
    onCancel: () => void;
    /** Campos extra (p. ej. un motivo) entre la descripción y los botones. */
    children?: ReactNode;
}) {
    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onCancel()}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                {children}
                <DialogFooter>
                    <Button variant="outline" onClick={onCancel}>
                        Cancelar
                    </Button>
                    <Button variant={destructive ? 'destructive' : 'default'} loading={loading} onClick={onConfirm}>
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
