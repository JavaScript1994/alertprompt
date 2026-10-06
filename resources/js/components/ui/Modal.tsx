import { X } from 'lucide-react';
import { useEffect, type ReactNode } from 'react';

const SIZES = {
    sm: 'max-w-sm',
    md: 'max-w-lg',
    lg: 'max-w-2xl',
    xl: 'max-w-4xl',
} as const;

export default function Modal({
    open,
    onClose,
    title,
    children,
    size = 'md',
}: {
    open: boolean;
    onClose: () => void;
    title?: string;
    children: ReactNode;
    size?: keyof typeof SIZES;
}) {
    useEffect(() => {
        if (!open) return;

        const handler = (event: KeyboardEvent) => {
            if (event.key === 'Escape') onClose();
        };

        document.addEventListener('keydown', handler);
        document.body.style.overflow = 'hidden';

        return () => {
            document.removeEventListener('keydown', handler);
            document.body.style.overflow = '';
        };
    }, [open, onClose]);

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div className="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onClick={onClose} />

            <div className={`relative w-full ${SIZES[size]} overflow-hidden rounded-xl bg-white shadow-2xl`}>
                {title && (
                    <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                        <h3 className="text-base font-semibold text-ink-900">{title}</h3>
                        <button
                            onClick={onClose}
                            className="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                )}
                <div className="max-h-[80vh] overflow-y-auto">{children}</div>
            </div>
        </div>
    );
}
