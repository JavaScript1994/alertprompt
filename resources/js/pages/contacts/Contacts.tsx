import { createColumnHelper } from '@tanstack/react-table';
import { CheckCircle2, Download, Info, Pencil, Search, ShieldCheck, UserPlus, Upload, Users, XCircle } from 'lucide-react';
import { useMemo, useRef, useState, type ChangeEvent, type FormEvent } from 'react';
import DataTable from '@/components/shared/DataTable';
import EmptyState from '@/components/shared/EmptyState';
import PageHeader from '@/components/shared/PageHeader';
import Pagination from '@/components/shared/Pagination';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useContacts, useImportContacts } from '@/hooks/useContacts';
import { apiErrorMessage } from '@/lib/format';
import type { Contact } from '@/types';
import ConsentsModal from './ConsentsModal';
import ContactFormModal from './ContactFormModal';

const columnHelper = createColumnHelper<Contact>();

function downloadTemplate(): void {
    const csv = [
        'name,phone,email,distrito',
        'Ana Torres,+51987654321,ana.torres@example.com,Miraflores',
        'Carlos Ramos,+51911223344,,San Isidro',
    ].join('\n');

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'plantilla_contactos.csv';
    link.click();
    URL.revokeObjectURL(url);
}

export default function Contacts() {
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState('');
    const [searchInput, setSearchInput] = useState('');
    const [isManualModalOpen, setIsManualModalOpen] = useState(false);
    const [editingContact, setEditingContact] = useState<Contact | null>(null);
    const [consentsContact, setConsentsContact] = useState<Contact | null>(null);

    const { data, isLoading, isFetching } = useContacts({ page, search });
    const importContacts = useImportContacts();
    const fileInputRef = useRef<HTMLInputElement>(null);

    const onSearchSubmit = (event: FormEvent) => {
        event.preventDefault();
        setPage(1);
        setSearch(searchInput);
    };

    const onFileSelected = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) return;
        importContacts.mutate(file);
    };

    const columns = useMemo(
        () => [
            columnHelper.accessor('name', {
                header: 'Nombre',
                cell: (info) => (
                    <p className="max-w-[220px] truncate font-medium text-foreground">{info.getValue()}</p>
                ),
            }),
            columnHelper.accessor('phone', {
                header: 'Teléfono',
                cell: (info) => <span className="whitespace-nowrap text-muted-foreground">{info.getValue() ?? '—'}</span>,
            }),
            columnHelper.accessor('email', {
                header: 'Email',
                cell: (info) => (
                    <p className="max-w-[220px] truncate text-muted-foreground">{info.getValue() ?? '—'}</p>
                ),
            }),
            columnHelper.display({
                id: 'actions',
                header: () => <span className="sr-only">Acciones</span>,
                meta: { className: 'w-24 text-right' },
                cell: (info) => {
                    const contact = info.row.original;
                    return (
                        <div className="inline-flex gap-1">
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="ghostsuccess"
                                        size="icon-sm"
                                        aria-label="Consentimientos"
                                        onClick={() => setConsentsContact(contact)}
                                    >
                                        <ShieldCheck />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>Consentimientos</TooltipContent>
                            </Tooltip>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label="Editar contacto"
                                        onClick={() => setEditingContact(contact)}
                                    >
                                        <Pencil />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>Editar contacto</TooltipContent>
                            </Tooltip>
                        </div>
                    );
                },
            }),
        ],
        [],
    );

    return (
        <div>
            <PageHeader
                title="Contactos"
                description={data ? `${data.meta.total.toLocaleString('es-PE')} contactos en tu base` : 'Cargando…'}
                actions={
                    <>
                        <Button variant="outline" onClick={() => setIsManualModalOpen(true)}>
                            <UserPlus />
                            Registro manual
                        </Button>
                        <Button variant="outline" onClick={downloadTemplate}>
                            <Download />
                            Descargar plantilla
                        </Button>
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept=".csv,text/csv"
                            className="hidden"
                            onChange={onFileSelected}
                        />
                        <Button onClick={() => fileInputRef.current?.click()} loading={importContacts.isPending}>
                            {!importContacts.isPending && <Upload />}
                            {importContacts.isPending ? 'Subiendo…' : 'Importar CSV'}
                        </Button>
                    </>
                }
            />

            <Alert variant="primary" className="mb-4">
                <Info />
                <AlertDescription>
                    <p>
                        El CSV necesita columnas <code className="font-mono font-semibold">name</code> (obligatoria) y
                        al menos una de <code className="font-mono font-semibold">phone</code> /{' '}
                        <code className="font-mono font-semibold">email</code>. Cualquier otra columna (como{' '}
                        <code className="font-mono font-semibold">distrito</code>) se guarda como dato extra del
                        contacto.{' '}
                        <button onClick={downloadTemplate} className="font-medium underline">
                            Descargá la plantilla de ejemplo
                        </button>
                        .
                    </p>
                </AlertDescription>
            </Alert>

            {importContacts.isError && (
                <Alert variant="error" className="mb-4">
                    <XCircle />
                    <AlertTitle>{apiErrorMessage(importContacts.error, [], 'Error al importar el archivo.')}</AlertTitle>
                </Alert>
            )}
            {importContacts.isSuccess && (
                <Alert variant="success" className="mb-4">
                    <CheckCircle2 />
                    <AlertTitle>Archivo recibido. Los contactos se están procesando.</AlertTitle>
                </Alert>
            )}

            <form onSubmit={onSearchSubmit} className="mb-4 flex gap-2">
                <div className="relative max-w-sm flex-1">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        placeholder="Buscar por nombre, teléfono o email"
                        className="bg-card pl-9"
                        value={searchInput}
                        onChange={(event) => setSearchInput(event.target.value)}
                    />
                </div>
                <Button type="submit" variant="outline">
                    Buscar
                </Button>
            </form>

            {isLoading && (
                <Card className="gap-4">
                    {Array.from({ length: 6 }).map((_, i) => (
                        <Skeleton key={i} className="h-8 w-full" />
                    ))}
                </Card>
            )}

            {!isLoading && data?.data.length === 0 && (
                <EmptyState
                    icon={Users}
                    title="No hay contactos"
                    description="Importá un CSV para empezar a construir tu base de contactos."
                />
            )}

            {!isLoading && data && data.data.length > 0 && (
                <>
                    <DataTable data={data.data} columns={columns} minWidth="min-w-[520px]" dimmed={isFetching} />
                    <Pagination meta={data.meta} noun="contactos" onPageChange={setPage} />
                </>
            )}

            <ContactFormModal open={isManualModalOpen} onClose={() => setIsManualModalOpen(false)} />
            <ContactFormModal
                open={editingContact !== null}
                onClose={() => setEditingContact(null)}
                contact={editingContact}
            />
            <ConsentsModal
                contactId={consentsContact?.id ?? null}
                contactName={consentsContact?.name}
                onClose={() => setConsentsContact(null)}
            />
        </div>
    );
}
