import { Search, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useContacts } from '@/hooks/useContacts';
import { cn } from '@/lib/utils';

interface ContactSummary {
    name: string;
    phone: string | null;
    email: string | null;
}

export default function ContactPicker({
    selected,
    onChange,
    initialDetails,
    error,
}: {
    selected: number[];
    onChange: (ids: number[]) => void;
    /** Nombres/teléfonos de contactos ya matriculados (edición), para mostrarlos
     *  como chips aunque no aparezcan en la página actual de la búsqueda. */
    initialDetails?: Record<number, ContactSummary>;
    error?: string | null;
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
        <div className="space-y-2">
            <div className="flex items-center justify-between">
                <Label>Destinatarios</Label>
                <span className="text-xs text-muted-foreground">{selected.length} seleccionados</span>
            </div>

            {selected.length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                    {selected.map((id) => (
                        <Badge key={id} variant="primary" className="py-1 pr-1">
                            {knownDetails[id]?.name ?? `Contacto #${id}`}
                            <button
                                type="button"
                                onClick={() => toggle(id)}
                                aria-label="Quitar"
                                className="rounded-full p-0.5 hover:bg-primary/15"
                            >
                                <X />
                            </button>
                        </Badge>
                    ))}
                </div>
            )}

            <div className={cn('overflow-hidden rounded-md border border-input', error && 'border-error')}>
                <div className="relative border-b">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        placeholder="Buscar contacto…"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        className="rounded-none border-0 pl-9 focus-visible:ring-0"
                    />
                </div>
                <div className="max-h-48 overflow-y-auto p-1.5">
                    {contacts.length === 0 && (
                        <p className="px-2 py-4 text-center text-sm text-muted-foreground">Sin contactos.</p>
                    )}
                    {contacts.map((contact) => (
                        <label
                            key={contact.id}
                            className="flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-sm hover:bg-muted"
                        >
                            <Checkbox checked={selected.includes(contact.id)} onCheckedChange={() => toggle(contact.id)} />
                            <span className="min-w-0 flex-1 truncate text-foreground">{contact.name}</span>
                            <span className="shrink-0 text-xs text-muted-foreground">
                                {contact.phone ?? contact.email}
                            </span>
                        </label>
                    ))}
                </div>
            </div>

            {error && <p className="text-sm text-error">{error}</p>}
        </div>
    );
}
