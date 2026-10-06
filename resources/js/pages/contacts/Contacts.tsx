import { AxiosError } from 'axios';
import { ChevronLeft, ChevronRight, Download, Pencil, Search, ShieldCheck, UserPlus, Upload, Users } from 'lucide-react';
import { useRef, useState } from 'react';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import EmptyState from '@/components/ui/EmptyState';
import Input from '@/components/ui/Input';
import PageHeader from '@/components/ui/PageHeader';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { useContacts, useImportContacts } from '@/hooks/useContacts';
import type { Contact } from '@/types';
import ConsentsModal from './ConsentsModal';
import ContactFormModal from './ContactFormModal';

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

    const onSearchSubmit = (event: React.FormEvent) => {
        event.preventDefault();
        setPage(1);
        setSearch(searchInput);
    };

    const onFileSelected = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) return;
        importContacts.mutate(file);
    };

    const importError =
        importContacts.error instanceof AxiosError
            ? (importContacts.error.response?.data?.message ?? 'Error al importar el archivo.')
            : null;

    const downloadTemplate = () => {
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
    };

    return (
        <div>
            <PageHeader
                title="Contactos"
                description={data ? `${data.meta.total.toLocaleString('es-PE')} contactos en tu base` : 'Cargando…'}
                actions={
                    <>
                        <Button variant="secondary" onClick={() => setIsManualModalOpen(true)}>
                            <UserPlus className="h-4 w-4" />
                            Registro manual
                        </Button>
                        <Button variant="secondary" onClick={downloadTemplate}>
                            <Download className="h-4 w-4" />
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
                            {!importContacts.isPending && <Upload className="h-4 w-4" />}
                            {importContacts.isPending ? 'Subiendo…' : 'Importar CSV'}
                        </Button>
                    </>
                }
            />

            <Alert type="info" className="mb-4">
                El CSV necesita columnas <code className="font-mono">name</code> (obligatoria) y al menos una de{' '}
                <code className="font-mono">phone</code> / <code className="font-mono">email</code>. Cualquier otra
                columna (como <code className="font-mono">distrito</code>) se guarda como dato extra del contacto.{' '}
                <button onClick={downloadTemplate} className="font-medium text-brand-700 underline">
                    Descargá la plantilla de ejemplo
                </button>
                .
            </Alert>

            <form onSubmit={onSearchSubmit} className="mb-4 flex gap-2">
                <div className="max-w-sm flex-1">
                    <Input
                        type="search"
                        placeholder="Buscar por nombre, teléfono o email"
                        icon={Search}
                        value={searchInput}
                        onChange={(event) => setSearchInput(event.target.value)}
                    />
                </div>
                <Button type="submit" variant="secondary">
                    Buscar
                </Button>
            </form>

            {importError && (
                <Alert type="error" className="mb-4">
                    {importError}
                </Alert>
            )}
            {importContacts.isSuccess && (
                <Alert type="success" className="mb-4">
                    Archivo recibido. Los contactos se están procesando.
                </Alert>
            )}

            {isLoading && (
                <div className="rounded-xl border border-slate-200 bg-white p-5">
                    <SkeletonTable rows={6} />
                </div>
            )}

            {!isLoading && data?.data.length === 0 && (
                <EmptyState
                    icon={Users}
                    title="No hay contactos"
                    description="Importá un CSV para empezar a construir tu base de contactos."
                />
            )}

            {!isLoading && data && data.data.length > 0 && (
                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[480px] text-left text-sm">
                            <thead className="border-b border-slate-200 bg-slate-50 text-xs font-medium text-slate-500 uppercase">
                                <tr>
                                    <th className="px-5 py-3">Nombre</th>
                                    <th className="px-5 py-3">Teléfono</th>
                                    <th className="px-5 py-3">Email</th>
                                    <th className="px-5 py-3 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {data.data.map((contact) => (
                                    <tr
                                        key={contact.id}
                                        className={`transition-opacity ${isFetching ? 'opacity-50' : ''}`}
                                    >
                                        <td className="max-w-[220px] truncate px-5 py-3.5 font-medium text-ink-900">
                                            {contact.name}
                                        </td>
                                        <td className="px-5 py-3.5 whitespace-nowrap text-slate-600">
                                            {contact.phone ?? '—'}
                                        </td>
                                        <td className="max-w-[220px] truncate px-5 py-3.5 text-slate-600">
                                            {contact.email ?? '—'}
                                        </td>
                                        <td className="px-5 py-3.5 text-right">
                                            <div className="inline-flex gap-1.5">
                                                <button
                                                    onClick={() => setConsentsContact(contact)}
                                                    title="Consentimientos"
                                                    className="inline-flex rounded-md p-1.5 text-slate-400 hover:bg-whatsapp-50 hover:text-whatsapp-700"
                                                >
                                                    <ShieldCheck className="h-4 w-4" />
                                                </button>
                                                <button
                                                    onClick={() => setEditingContact(contact)}
                                                    title="Editar contacto"
                                                    className="inline-flex rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {data && data.meta.last_page > 1 && (
                <div className="mt-4 flex items-center justify-between text-sm text-slate-600">
                    <span>
                        Página {data.meta.current_page} de {data.meta.last_page}
                    </span>
                    <div className="flex gap-2">
                        <Button
                            variant="secondary"
                            size="sm"
                            onClick={() => setPage((p) => Math.max(1, p - 1))}
                            disabled={data.meta.current_page <= 1}
                        >
                            <ChevronLeft className="h-4 w-4" /> Anterior
                        </Button>
                        <Button
                            variant="secondary"
                            size="sm"
                            onClick={() => setPage((p) => Math.min(data.meta.last_page, p + 1))}
                            disabled={data.meta.current_page >= data.meta.last_page}
                        >
                            Siguiente <ChevronRight className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
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
