import {
    flexRender,
    getCoreRowModel,
    getSortedRowModel,
    useReactTable,
    type ColumnDef,
    type SortingState,
} from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import { useState } from 'react';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';

declare module '@tanstack/react-table' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface ColumnMeta<TData, TValue> {
        /** Clases extra para <th> y <td> de la columna (ancho, alineación). */
        className?: string;
    }
}

/**
 * Tabla de TanStack Table con los estilos de la plantilla. Ordena en cliente
 * sobre la página actual; la paginación real la hace el backend.
 */
export default function DataTable<TData>({
    data,
    columns,
    minWidth = 'min-w-[640px]',
    dimmed = false,
}: {
    data: TData[];
    // ColumnDef heterogénea (cada columna tiene su propio TValue), como en la doc de TanStack.
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    columns: ColumnDef<TData, any>[];
    minWidth?: string;
    /** Atenúa las filas mientras se recarga la página (isFetching). */
    dimmed?: boolean;
}) {
    const [sorting, setSorting] = useState<SortingState>([]);

    const table = useReactTable({
        data,
        columns,
        state: { sorting },
        onSortingChange: setSorting,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
    });

    return (
        <div className="overflow-hidden rounded-xl border bg-card shadow-md">
            <Table className={minWidth}>
                <TableHeader className="bg-muted/60">
                    {table.getHeaderGroups().map((headerGroup) => (
                        <TableRow key={headerGroup.id} className="hover:bg-transparent">
                            {headerGroup.headers.map((header) => {
                                const sortDirection = header.column.getIsSorted();

                                return (
                                    <TableHead key={header.id} className={header.column.columnDef.meta?.className}>
                                        {header.column.getCanSort() ? (
                                            <button
                                                onClick={header.column.getToggleSortingHandler()}
                                                className="inline-flex items-center gap-1 uppercase hover:text-foreground"
                                            >
                                                {flexRender(header.column.columnDef.header, header.getContext())}
                                                {sortDirection === 'asc' && <ArrowUp className="size-3" />}
                                                {sortDirection === 'desc' && <ArrowDown className="size-3" />}
                                                {!sortDirection && <ArrowUpDown className="size-3 opacity-40" />}
                                            </button>
                                        ) : (
                                            flexRender(header.column.columnDef.header, header.getContext())
                                        )}
                                    </TableHead>
                                );
                            })}
                        </TableRow>
                    ))}
                </TableHeader>
                <TableBody className={cn('transition-opacity', dimmed && 'opacity-50')}>
                    {table.getRowModel().rows.map((row) => (
                        <TableRow key={row.id}>
                            {row.getVisibleCells().map((cell) => (
                                <TableCell key={cell.id} className={cell.column.columnDef.meta?.className}>
                                    {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                </TableCell>
                            ))}
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
