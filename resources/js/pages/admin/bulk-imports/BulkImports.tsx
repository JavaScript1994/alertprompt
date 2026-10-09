import { createColumnHelper } from '@tanstack/react-table';
import { Eye, Plus, Upload } from 'lucide-react';
import { useMemo, useState } from 'react';
import DataTable from '@/components/shared/DataTable';
import EmptyState from '@/components/shared/EmptyState';
import PageHeader from '@/components/shared/PageHeader';
import Pagination from '@/components/shared/Pagination';
import { Badge, type BadgeVariant } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { useBulkImport, useBulkImports } from '@/hooks/useBulkImports';
import { useCan } from '@/hooks/usePermissions';
import { formatDateTime } from '@/lib/format';
import type { BulkImport } from '@/types';
import UploadBulkDialog from './UploadBulkDialog';

const STATUS: Record<BulkImport['status'], { label: string; variant: BadgeVariant }> = {
    queued: { label: 'En cola', variant: 'neutral' },
    processing: { label: 'Procesando', variant: 'info' },
    completed: { label: 'Completada', variant: 'success' },
    failed: { label: 'Falló', variant: 'error' },
};

const n = (value: number) => value.toLocaleString('es-PE');
const column = createColumnHelper<BulkImport>();

function Detail({ id, onClose }: { id: number | null; onClose: () => void }) {
    const { data } = useBulkImport(id);

    const rows: [string, number][] = data
        ? [
              ['Filas leídas', data.totals.rows],
              ['Contactos nuevos', data.totals.created],
              ['Contactos actualizados', data.totals.updated],
              ['Consentimientos registrados', data.totals.consents_recorded],
              ['Contactos sin consentimiento', data.totals.without_consent],
              ['Consentimientos bloqueados (bajas)', data.totals.consents_blocked],
              ['Filas inválidas', data.totals.invalid],
          ]
        : [];

    return (
        <Dialog open={id !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{data?.original_filename ?? 'Carga masiva'}</DialogTitle>
                    <DialogDescription>
                        {data && `${data.tenant?.name ?? '—'} · subida por ${data.uploaded_by ?? '—'} el ${formatDateTime(data.created_at)}`}
                    </DialogDescription>
                </DialogHeader>
                {!data ? (
                    <Skeleton className="h-48 w-full" />
                ) : (
                    <div className="space-y-5 text-sm">
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-2">
                            {rows.map(([label, value]) => (
                                <div key={label} className="flex justify-between gap-3 border-b py-1.5">
                                    <dt className="text-muted-foreground">{label}</dt>
                                    <dd className="font-medium tabular-nums">{n(value)}</dd>
                                </div>
                            ))}
                        </dl>
                        <div>
                            <p className="text-xs text-muted-foreground">Origen declarado</p>
                            <p>{data.declared_source}</p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">Declaración aceptada</p>
                            <p className="text-muted-foreground italic">{data.attestation_text}</p>
                        </div>
                        {data.errors && data.errors.length > 0 && (
                            <div>
                                <p className="mb-2 font-semibold">Observaciones por fila</p>
                                <ul className="max-h-64 space-y-1 overflow-y-auto rounded-lg border p-3 font-mono text-xs">
                                    {data.errors.map((error, index) => (
                                        <li key={index}>
                                            <span className="text-muted-foreground">Línea {error.line}:</span> {error.message}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}

export default function BulkImports() {
    const [page, setPage] = useState(1);
    const [isUploading, setIsUploading] = useState(false);
    const [detailId, setDetailId] = useState<number | null>(null);
    const { data, isLoading } = useBulkImports(page);
    const canCreate = useCan()('admin.bulk_imports.create');

    const columns = useMemo(
        () => [
            column.accessor('created_at', { header: 'Fecha', cell: (info) => formatDateTime(info.getValue(), 'short') }),
            column.accessor('tenant', { header: 'Cliente', cell: (info) => info.getValue()?.name ?? '—' }),
            column.accessor('original_filename', {
                header: 'Archivo',
                cell: (info) => <span className="block max-w-[200px] truncate">{info.getValue()}</span>,
            }),
            column.accessor('status', {
                header: 'Estado',
                cell: (info) => <Badge variant={STATUS[info.getValue()].variant}>{STATUS[info.getValue()].label}</Badge>,
            }),
            column.display({
                id: 'imported',
                header: 'Contactos',
                meta: { className: 'text-right tabular-nums' },
                cell: (info) => n(info.row.original.totals.created + info.row.original.totals.updated),
            }),
            column.display({
                id: 'consents',
                header: 'Con consentimiento',
                meta: { className: 'text-right tabular-nums' },
                cell: (info) => n(info.row.original.totals.consents_recorded),
            }),
            column.display({
                id: 'issues',
                header: 'Observaciones',
                meta: { className: 'text-right tabular-nums' },
                cell: (info) => n(info.row.original.errors_count),
            }),
            column.display({
                id: 'view',
                header: '',
                meta: { className: 'w-[1%] text-right' },
                cell: (info) => (
                    <Button variant="ghost" size="icon-sm" aria-label="Ver detalle" onClick={() => setDetailId(info.row.original.id)}>
                        <Eye />
                    </Button>
                ),
            }),
        ],
        [],
    );

    return (
        <div>
            <PageHeader
                title="Cargas masivas"
                description="Contactos importados a nombre de un cliente, con evidencia de consentimiento por fila."
                actions={
                    canCreate && (
                        <Button onClick={() => setIsUploading(true)}>
                            <Plus />
                            Nueva carga
                        </Button>
                    )
                }
            />

            {isLoading ? (
                <Skeleton className="h-64 w-full rounded-xl" />
            ) : data && data.data.length > 0 ? (
                <>
                    <DataTable data={data.data} columns={columns} minWidth="min-w-[820px]" />
                    <Pagination meta={data.meta} noun="cargas" onPageChange={setPage} />
                </>
            ) : (
                <EmptyState icon={Upload} title="Aún no hay cargas masivas" />
            )}

            <UploadBulkDialog open={isUploading} onClose={() => setIsUploading(false)} />
            <Detail id={detailId} onClose={() => setDetailId(null)} />
        </div>
    );
}
