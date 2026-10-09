import { Badge } from '@/components/ui/badge';
import type { Invoice } from '@/types';

export const money = (value: string | number, currency = 'PEN') =>
    Number(value).toLocaleString('es-PE', { style: 'currency', currency });

export function shortDate(iso: string): string {
    const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('es-PE', { day: 'numeric', month: 'short', year: 'numeric' });
}

export function InvoiceStatusBadge({ invoice }: { invoice: Pick<Invoice, 'status' | 'is_overdue'> }) {
    if (invoice.status === 'paid') return <Badge variant="success">Pagado</Badge>;
    if (invoice.status === 'void') return <Badge variant="neutral">Anulado</Badge>;
    if (invoice.is_overdue) return <Badge variant="error">Vencido</Badge>;
    return <Badge variant="warning">Pendiente</Badge>;
}
