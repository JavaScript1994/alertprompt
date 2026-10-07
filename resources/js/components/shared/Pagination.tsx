import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { PaginationMeta } from '@/types';

export default function Pagination({
    meta,
    noun,
    onPageChange,
}: {
    meta: PaginationMeta;
    /** Sustantivo en plural para el total, ej. "campañas". */
    noun?: string;
    onPageChange: (page: number) => void;
}) {
    if (meta.last_page <= 1) return null;

    return (
        <div className="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground">
            <span>
                Página {meta.current_page} de {meta.last_page}
                {noun && ` · ${meta.total.toLocaleString('es-PE')} ${noun}`}
            </span>
            <div className="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onPageChange(Math.max(1, meta.current_page - 1))}
                    disabled={meta.current_page <= 1}
                >
                    <ChevronLeft /> Anterior
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onPageChange(Math.min(meta.last_page, meta.current_page + 1))}
                    disabled={meta.current_page >= meta.last_page}
                >
                    Siguiente <ChevronRight />
                </Button>
            </div>
        </div>
    );
}
