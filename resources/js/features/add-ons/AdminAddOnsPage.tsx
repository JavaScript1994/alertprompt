import { Heart, Pencil, Plus, Power, PowerOff, Puzzle, Users, XCircle } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import ConfirmDialog from '@/components/shared/ConfirmDialog';
import EmptyState from '@/components/shared/EmptyState';
import PageHeader from '@/components/shared/PageHeader';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { useModuleCatalog } from '@/hooks/useModulesAdmin';
import { useCan } from '@/hooks/usePermissions';
import { apiErrorMessage, formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ModuleKey } from '@/types';
import { useAddOnCatalog, useAddOnInterests, useSaveAddOn, useSetAddOnActive } from './adminApi';
import { ADD_ON_ICONS, addOnPrice } from './meta';
import type { AddOn, AddOnStatus } from './types';

/** Alta (sin `addOn`) o edición de un servicio. */
function AddOnDialog({ addOn, onClose }: { addOn?: AddOn; onClose: () => void }) {
    const save = useSaveAddOn();
    const { data: modules } = useModuleCatalog();
    // Con clientes que lo tienen, el backend no deja cambiar módulo ni estado.
    const locked = (addOn?.active_contracts_count ?? 0) > 0;
    const [form, setForm] = useState({
        name: addOn?.name ?? '',
        description: addOn?.description ?? '',
        status: addOn?.status ?? ('available' as AddOnStatus),
        module: addOn?.module ?? '',
        monthly_price: addOn?.monthly_price ?? '',
    });
    const set = (key: keyof typeof form, value: string) => setForm((current) => ({ ...current, [key]: value }));
    const isAvailable = form.status === 'available';

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();
        save.mutate(
            {
                id: addOn?.id,
                name: form.name,
                description: form.description || null,
                status: form.status,
                module: isAvailable && form.module ? (form.module as ModuleKey) : null,
                monthly_price: isAvailable && form.monthly_price !== '' ? form.monthly_price : null,
            },
            { onSuccess: onClose },
        );
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>{addOn ? `Editar ${addOn.name}` : 'Nuevo servicio'}</DialogTitle>
                    <DialogDescription>
                        {addOn
                            ? 'Un precio nuevo aplica a las contrataciones que se hagan desde ahora; los contratos vigentes conservan el suyo.'
                            : 'Uno disponible activa un módulo al contratarse. Uno próximo solo se muestra y junta interesados.'}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={onSubmit} className="space-y-4">
                    {save.isError && (
                        <Alert variant="error">
                            <XCircle />
                            <AlertTitle>{apiErrorMessage(save.error, ['name', 'status', 'module', 'monthly_price'], 'No se pudo guardar el servicio.')}</AlertTitle>
                        </Alert>
                    )}
                    <Field label="Nombre" htmlFor="add-on-name">
                        <Input id="add-on-name" maxLength={60} value={form.name} onChange={(e) => set('name', e.target.value)} />
                    </Field>
                    <Field label="Descripción" htmlFor="add-on-desc">
                        <Input id="add-on-desc" maxLength={255} value={form.description} onChange={(e) => set('description', e.target.value)} />
                    </Field>
                    <Field label="Estado" htmlFor="add-on-status" hint={locked ? 'Hay clientes con este servicio: no se puede cambiar.' : undefined}>
                        <Select value={form.status} onValueChange={(v) => v && set('status', v)} disabled={locked}>
                            <SelectTrigger id="add-on-status" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="available">Disponible: se puede solicitar</SelectItem>
                                <SelectItem value="coming_soon">Próximamente: botón "Me interesa"</SelectItem>
                            </SelectContent>
                        </Select>
                    </Field>
                    {isAvailable && (
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field label="Módulo que activa" htmlFor="add-on-module">
                                <Select value={form.module} onValueChange={(v) => v && set('module', v)} disabled={locked}>
                                    <SelectTrigger id="add-on-module" className="w-full">
                                        <SelectValue placeholder="Elige un módulo" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {modules?.map((module) => (
                                            <SelectItem key={module.key} value={module.key}>
                                                {module.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field label="Precio mensual (S/ sin IGV)" htmlFor="add-on-price">
                                <Input id="add-on-price" type="number" min="0" step="0.01" value={form.monthly_price} onChange={(e) => set('monthly_price', e.target.value)} />
                            </Field>
                        </div>
                    )}
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            Cancelar
                        </Button>
                        <Button type="submit" loading={save.isPending} disabled={!form.name.trim()}>
                            {addOn ? 'Guardar servicio' : 'Crear servicio'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function InterestsDialog({ addOn, onClose }: { addOn: AddOn; onClose: () => void }) {
    const { data, isLoading } = useAddOnInterests(addOn.id);

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Interesados en {addOn.name}</DialogTitle>
                    <DialogDescription>Clientes que marcaron "Me interesa".</DialogDescription>
                </DialogHeader>
                {isLoading || !data ? (
                    <Skeleton className="h-32 w-full" />
                ) : data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">Aún nadie.</p>
                ) : (
                    <ul className="max-h-80 divide-y overflow-y-auto">
                        {data.map((interest) => (
                            <li key={interest.id} className="py-2.5 text-sm">
                                <Link to={`/admin/clients/${interest.tenant.id}`} className="font-medium text-foreground hover:text-primary">
                                    {interest.tenant.name}
                                </Link>
                                <p className="text-xs text-muted-foreground">
                                    {interest.user ?? '—'} · {formatDateTime(interest.created_at, 'short')}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </DialogContent>
        </Dialog>
    );
}

export default function AdminAddOnsPage() {
    const { data, isLoading } = useAddOnCatalog();
    const { data: modules } = useModuleCatalog();
    const setActive = useSetAddOnActive();
    const canManage = useCan()('admin.memberships.manage');
    const [editing, setEditing] = useState<AddOn | null>(null);
    const [isCreating, setIsCreating] = useState(false);
    const [deactivating, setDeactivating] = useState<AddOn | null>(null);
    const [viewingInterests, setViewingInterests] = useState<AddOn | null>(null);
    const moduleLabel = (key: string) => modules?.find((module) => module.key === key)?.label ?? key;

    return (
        <div>
            <PageHeader
                title="Servicios"
                description="Servicios adicionales que los clientes ven en Configuración → Servicios."
                actions={
                    canManage && (
                        <Button onClick={() => setIsCreating(true)}>
                            <Plus />
                            Nuevo servicio
                        </Button>
                    )
                }
            />

            <Alert variant="info" className="mb-6">
                <AlertDescription>
                    Un servicio contratado se suma a la factura del plan desde el siguiente periodo; en membresías anuales, los
                    meses que faltan del año se cobran en un comprobante aparte al contratarlo. Desactivarlo deja de ofrecerlo,
                    pero los clientes que lo tienen lo conservan. Los servicios no se eliminan para no perder el historial.
                </AlertDescription>
            </Alert>

            {isLoading || !data ? (
                <Skeleton className="h-72 w-full rounded-xl" />
            ) : data.length === 0 ? (
                <EmptyState icon={Puzzle} title="Sin servicios" description="Crea el primero con «Nuevo servicio»." />
            ) : (
                <div className="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                    {data.map((addOn) => {
                        const Icon = ADD_ON_ICONS[addOn.key] ?? Puzzle;
                        const isUpcoming = addOn.status === 'coming_soon';

                        return (
                            <Card key={addOn.id} className={cn('gap-4 p-6', isUpcoming && 'border-dashed', !addOn.is_active && 'bg-muted/40')}>
                                <div className="flex items-start justify-between gap-3">
                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-lightprimary text-primary dark:text-brand-200">
                                        <Icon className="size-5" />
                                    </span>
                                    <div className="flex flex-wrap justify-end gap-1.5">
                                        {isUpcoming ? <Badge variant="info">Próximamente</Badge> : <Badge variant="success">Disponible</Badge>}
                                        {!addOn.is_active && <Badge variant="neutral">Inactivo</Badge>}
                                    </div>
                                </div>
                                <div>
                                    <p className="font-semibold text-foreground">{addOn.name}</p>
                                    {addOn.description && <p className="mt-1 text-sm text-muted-foreground">{addOn.description}</p>}
                                </div>
                                {!isUpcoming && (
                                    <div>
                                        <p>
                                            <span className="text-2xl font-semibold tracking-tight text-foreground tabular-nums">{addOnPrice(addOn.monthly_price)}</span>
                                            <span className="ml-1 text-sm text-muted-foreground">/ mes + IGV</span>
                                        </p>
                                        {addOn.module && <p className="text-xs text-muted-foreground">Activa el módulo «{moduleLabel(addOn.module)}»</p>}
                                    </div>
                                )}
                                <div className="mt-auto space-y-3 border-t pt-4">
                                    {isUpcoming ? (
                                        <button
                                            type="button"
                                            className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-primary"
                                            onClick={() => setViewingInterests(addOn)}
                                        >
                                            <Heart className="size-4" />
                                            {addOn.interests_count === 1 ? '1 cliente interesado' : `${addOn.interests_count ?? 0} clientes interesados`}
                                        </button>
                                    ) : (
                                        <p className="inline-flex items-center gap-1.5 text-sm text-muted-foreground">
                                            <Users className="size-4" />
                                            {addOn.active_contracts_count === 1 ? '1 cliente lo tiene' : `${addOn.active_contracts_count ?? 0} clientes lo tienen`}
                                        </p>
                                    )}
                                    {canManage && (
                                        <div className="flex gap-2">
                                            <Button variant="outline" size="sm" onClick={() => setEditing(addOn)}>
                                                <Pencil />
                                                Editar
                                            </Button>
                                            {addOn.is_active ? (
                                                <Button variant="outline" size="sm" className="text-error hover:text-error" onClick={() => setDeactivating(addOn)}>
                                                    <PowerOff />
                                                    Desactivar
                                                </Button>
                                            ) : (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    loading={setActive.isPending && setActive.variables?.id === addOn.id}
                                                    onClick={() => setActive.mutate({ id: addOn.id, active: true })}
                                                >
                                                    <Power />
                                                    Activar
                                                </Button>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </Card>
                        );
                    })}
                </div>
            )}

            {isCreating && <AddOnDialog onClose={() => setIsCreating(false)} />}
            {editing && <AddOnDialog addOn={editing} onClose={() => setEditing(null)} />}
            {viewingInterests && <InterestsDialog addOn={viewingInterests} onClose={() => setViewingInterests(null)} />}

            <ConfirmDialog
                open={deactivating !== null}
                title={`Desactivar ${deactivating?.name ?? 'servicio'}`}
                description={
                    deactivating?.active_contracts_count
                        ? `Los clientes ya no lo verán. Los ${deactivating.active_contracts_count} cliente(s) que lo tienen lo conservan.`
                        : 'Los clientes ya no lo verán. Puedes volver a activarlo cuando quieras.'
                }
                confirmLabel="Desactivar"
                destructive
                loading={setActive.isPending}
                onConfirm={() => deactivating && setActive.mutate({ id: deactivating.id, active: false }, { onSettled: () => setDeactivating(null) })}
                onCancel={() => setDeactivating(null)}
            />
        </div>
    );
}
