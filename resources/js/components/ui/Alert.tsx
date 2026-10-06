import { AlertCircle, CheckCircle2, Info, TriangleAlert } from 'lucide-react';
import type { ReactNode } from 'react';

export type AlertType = 'error' | 'success' | 'warning' | 'info';

const styles: Record<AlertType, string> = {
    error: 'bg-red-50 text-red-700 border-red-200',
    success: 'bg-whatsapp-50 text-whatsapp-800 border-whatsapp-200',
    warning: 'bg-amber-50 text-amber-700 border-amber-200',
    info: 'bg-blue-50 text-blue-700 border-blue-200',
};

const icons: Record<AlertType, ReactNode> = {
    error: <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />,
    success: <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" />,
    warning: <TriangleAlert className="mt-0.5 h-4 w-4 shrink-0" />,
    info: <Info className="mt-0.5 h-4 w-4 shrink-0" />,
};

export default function Alert({
    type = 'info',
    children,
    className = '',
}: {
    type?: AlertType;
    children: ReactNode;
    className?: string;
}) {
    if (!children) return null;

    return (
        <div className={`flex items-start gap-2.5 rounded-lg border p-3.5 text-sm ${styles[type]} ${className}`}>
            {icons[type]}
            <span className="leading-relaxed">{children}</span>
        </div>
    );
}
