import { Search, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import Input from '@/components/ui/Input';
import { useContacts } from '@/hooks/useContacts';

interface ContactSummary {
    name: string;
    phone: string | null;
    email: string | null;
}

export default function ContactPicker({
    selected,
    onChange,
    initialDetails,
}: {
    selected: number[];
    onChange: (ids: number[]) => void;
    /** Nombres/teléfonos de contactos ya matriculados (edición), para mostrarlos
     *  como chips aunque no aparezcan en la página actual de la búsqueda. */
    initialDetails?: Record<number, ContactSummary>;
}) {
    const [search, setSearch] = useState('');
    const { data } = useContacts({ page: 1, search });
    const contacts = data?.data ?? [];

    // Recordamos los datos de cualquier contacto que haya pasado por acá
    // (precargados o encontrados por búsqueda) para poder listarlo como chip
    // seleccionado sin depender de que siga en los resultados de búsqueda.
    const [knownDetails, setKnownDetails] = useState<Record<number, ContactSummary>>(initialDetails ?? {});

    useEffect(() => {
        if (initialDetails) {
            setKnownDetails((prev) => ({ ...initialDetails, ...prev }));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [initialDetails]);

    useEffect(() => {
        if (contacts.length === 0) return;
        setKnownDetails((prev) => {
            const next = { ...prev };
            for (const contact of contacts) {
                next[contact.id] = { name: contact.name, phone: contact.phone, email: contact.email };
            }
            return next;
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [contacts]);

    const toggle = (id: number) => {
        onChange(selected.includes(id) ? selected.filter((c) => c !== id) : [...selected, id]);
    };

    return (
        <div>
            <div className="mb-2 flex items-center justify-between">
                <label className="block text-sm font-medium text-slate-700">Destinatarios</label>
                <span className="text-xs text-slate-500">{selected.length} seleccionados</span>
            </div>

            {selected.length > 0 && (
                <div className="mb-2 flex flex-wrap gap-1.5">
                    {selected.map((id) => (
                        <span
                            key={id}
                            className="inline-flex items-center gap-1 rounded-full bg-brand-50 py-1 pr-1.5 pl-2.5 text-xs font-medium text-brand-700"
                        >
                            {knownDetails[id]?.name ?? `Contacto #${id}`}
                            <button
                                type="button"
                                onClick={() => toggle(id)}
                                className="rounded-full p-0.5 hover:bg-brand-100"
                            >
                                <X className="h-3 w-3" />
                            </button>
                        </span>
                    ))}
                </div>
            )}

            <div className="rounded-lg border border-slate-300">
                <div className="border-b border-slate-200 p-2">
                    <Input
                        icon={Search}
                        placeholder="Buscar contacto…"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        className="border-0 focus:ring-0"
                    />
                </div>
                <div className="max-h-48 overflow-y-auto p-1.5">
                    {contacts.length === 0 && (
                        <p className="px-2 py-4 text-center text-sm text-slate-400">Sin contactos.</p>
                    )}
                    {contacts.map((contact) => (
                        <label
                            key={contact.id}
                            className="flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-sm hover:bg-slate-50"
                        >
                            <input
                                type="checkbox"
                                checked={selected.includes(contact.id)}
                                onChange={() => toggle(contact.id)}
                                className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-2 focus:ring-brand-500/30"
                            />
                            <span className="min-w-0 flex-1 truncate text-slate-800">{contact.name}</span>
                            <span className="shrink-0 text-xs text-slate-400">{contact.phone ?? contact.email}</span>
                        </label>
                    ))}
                </div>
            </div>
        </div>
    );
}
