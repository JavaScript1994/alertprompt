import { FileText } from 'lucide-react';
import { useState } from 'react';
import EmptyState from '@/components/shared/EmptyState';
import PageHeader from '@/components/shared/PageHeader';
import Pagination from '@/components/shared/Pagination';
import Tabs from '@/components/shared/Tabs';
import { Skeleton } from '@/components/ui/skeleton';
import { useAdminInvoices } from '@/hooks/useBilling';
import InvoicesTable from './InvoicesTable';

type StatusTab = 'all' | 'issued' | 'overdue' | 'paid' | 'void';

export default function AdminInvoices() {
    const [status, setStatus] = useState<StatusTab>('all');
    const [page, setPage] = useState(1);
    const { data, isLoading } = useAdminInvoices({ page, status: status === 'all' ? undefined : status });

    return (
        <div>
            <PageHeader
                title="Comprobantes"
                description="Comprobantes de todos los clientes. Se emiten solos con cada período de la membresía; también se pueden emitir a mano desde la ficha del cliente."
            />
            <Tabs
                tabs={[
                    { key: 'all', label: 'Todos' },
                    { key: 'issued', label: 'Pendientes' },
                    { key: 'overdue', label: 'Vencidos' },
                    { key: 'paid', label: 'Pagados' },
                    { key: 'void', label: 'Anulados' },
                ]}
                active={status}
                onChange={(key) => {
                    setStatus(key);
                    setPage(1);
                }}
            />
            {isLoading ? (
                <Skeleton className="h-64 w-full rounded-xl" />
            ) : data && data.data.length > 0 ? (
                <>
                    <InvoicesTable invoices={data.data} />
                    <Pagination meta={data.meta} noun="comprobantes" onPageChange={setPage} />
                </>
            ) : (
                <EmptyState icon={FileText} title="Sin comprobantes en esta vista" />
            )}
        </div>
    );
}
