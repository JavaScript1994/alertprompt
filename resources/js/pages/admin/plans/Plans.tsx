import { Pencil, Plus, Power, PowerOff, XCircle } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import ConfirmDialog from '@/components/shared/ConfirmDialog';
import PageHeader from '@/components/shared/PageHeader';
import PlanCards from '@/components/shared/PlanCards';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { useCreatePlan, usePlans, useSetPlanActive, useUpdatePlan } from '@/hooks/useMemberships';
import { useModuleCatalog } from '@/hooks/useModulesAdmin';
import { useCan } from '@/hooks/usePermissions';
import { apiErrorMessage } from '@/lib/format';
import type { Plan } from '@/types';

const toNumber = (value: string) => (value.trim() === '' ? null : Number(value));

/** Alta (sin `plan`) o edición de un plan. */
function PlanDialog({ plan, onClose }: { plan?: Plan; onClose: () => void }) {
    const isEditing = plan !== undefined;
    const create = useCreatePlan();
    const update = useUpdatePlan();
    const mutation = isEditing ? update : create;
    const { data: catalog } = useModuleCatalog();
    const [form, setForm] = useState({
        name: plan?.name ?? '',
        description: plan?.description ?? '',
        monthly_price: plan?.monthly_price ?? '',
        whatsapp: plan?.quotas.whatsapp?.toString() ?? '',
        sms: plan?.quotas.sms?.toString() ?? '',
        email: plan?.quotas.email?.toString() ?? '',
        is_public: plan?.is_public ?? true,
    });
    const [modules, setModules] = useState<string[]>(plan?.modules ?? ['sms', 'email']);
    const set = (key: keyof typeof form, value: string | boolean) => setForm((current) => ({ ...current, [key]: value }));

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();
        const input = {
            name: form.name,
            description: form.description || null,
            monthly_price: form.monthly_price === '' ? null : form.monthly_price,
            quotas: { whatsapp: toNumber(form.whatsapp), sms: toNumber(form.sms), email: toNumber(form.email) },
            modules,
            is_public: form.is_public,
        };

        if (isEditing) update.mutate({ ...input, id: plan.id }, { onSuccess: onClose });
        else create.mutate(input, { onSuccess: onClose });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] max-w-lg overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{isEditing ? `Editar plan ${plan.name}` : 'Nuevo plan'}</DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Los cambios aplican a las membresías que se creen desde ahora; las vigentes conservan su precio y cuotas.'
                            : 'Queda activo y disponible para nuevas membresías y clientes.'}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={onSubmit} className="space-y-4">
                    {mutation.isError && (
                        <Alert variant="error">
                            <XCircle />
                            <AlertTitle>
                                {apiErrorMessage(mutation.error, ['name', 'monthly_price', 'quotas', 'modules'], 'No se pudo guardar el plan.')}
                            </AlertTitle>
                        </Alert>
                    )}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Field label="Nombre" htmlFor="plan-name">
                            <Input id="plan-name" value={form.name} onChange={(e) => set('name', e.target.value)} />
                        </Field>
                        <Field label="Precio mensual (S/ sin IGV)" htmlFor="plan-price" hint="Vacío = a medida.">
                            <Input id="plan-price" type="number" min="0" step="0.01" value={form.monthly_price} onChange={(e) => set('monthly_price', e.target.value)} />
                        </Field>
                    </div>
                    <Field label="Descripción" htmlFor="plan-desc">
                        <Input id="plan-desc" value={form.description} onChange={(e) => set('description', e.target.value)} />
                    </Field>
                    <fieldset className="grid grid-cols-3 gap-4 rounded-lg border p-4">
                        <legend className="px-1 text-sm font-semibold">Mensajes por mes (vacío = sin límite)</legend>
                        <Field label="WhatsApp" htmlFor="plan-wa">
                            <Input id="plan-wa" type="number" min="0" value={form.whatsapp} onChange={(e) => set('whatsapp', e.target.value)} />
                        </Field>
                        <Field label="SMS" htmlFor="plan-sms">
                            <Input id="plan-sms" type="number" min="0" value={form.sms} onChange={(e) => set('sms', e.target.value)} />
                        </Field>
                        <Field label="Email" htmlFor="plan-email">
                            <Input id="plan-email" type="number" min="0" value={form.email} onChange={(e) => set('email', e.target.value)} />
                        </Field>
                    </fieldset>
                    <fieldset className="space-y-2.5 rounded-lg border p-4">
                        <legend className="px-1 text-sm font-semibold">Módulos incluidos al crear un cliente</legend>
                        {catalog?.map((module) => (
                            <div key={module.key} className="flex items-center gap-2.5">
                                <Checkbox
                                    id={`plan-module-${module.key}`}
                                    checked={modules.includes(module.key)}
                                    onCheckedChange={(checked) =>
                                        setModules((current) => (checked === true ? [...current, module.key] : current.filter((key) => key !== module.key)))
                                    }
                                />
                                <Label htmlFor={`plan-module-${module.key}`} className="cursor-pointer font-normal">
                                    {module.label}
                                </Label>
                            </div>
                        ))}
                    </fieldset>
                    <div className="flex items-center gap-2.5">
                        <Checkbox id="plan-public" checked={form.is_public} onCheckedChange={(checked) => set('is_public', checked === true)} />
                        <Label htmlFor="plan-public" className="cursor-pointer font-normal">
                            Mostrar a los clientes en su pantalla de Membresía
                        </Label>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            Cancelar
                        </Button>
                        <Button type="submit" loading={mutation.isPending} disabled={!form.name.trim()}>
                            {isEditing ? 'Guardar plan' : 'Crear plan'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Plans() {
    const { data: plans, isLoading } = usePlans();
    const setActive = useSetPlanActive();
    const canManage = useCan()('admin.memberships.manage');
    const [editing, setEditing] = useState<Plan | null>(null);
    const [isCreating, setIsCreating] = useState(false);
    const [deactivating, setDeactivating] = useState<Plan | null>(null);

    return (
        <div>
            <PageHeader
                title="Planes"
                description="Catálogo que ven los clientes en Membresía y que se propone al crear una membresía."
                actions={
                    canManage && (
                        <Button onClick={() => setIsCreating(true)}>
                            <Plus />
                            Nuevo plan
                        </Button>
                    )
                }
            />

            <Alert variant="info" className="mb-6">
                <AlertDescription>
                    Borde punteado: no se muestra a los clientes (solo a quien ya lo tiene). Un plan desactivado deja de
                    ofrecerse, pero los clientes que lo tienen lo conservan. Los planes no se eliminan para no perder el
                    historial de membresías y comprobantes.
                </AlertDescription>
            </Alert>

            {isLoading || !plans ? (
                <Skeleton className="h-72 w-full rounded-xl" />
            ) : (
                <PlanCards
                    plans={plans}
                    footer={(plan) => (
                        <div className="mt-auto space-y-3">
                            <p className="text-xs text-muted-foreground">
                                {plan.clients_count === 1 ? '1 cliente con este plan' : `${plan.clients_count ?? 0} clientes con este plan`}
                            </p>
                            {canManage && (
                                <div className="flex gap-2">
                                    <Button variant="outline" size="sm" onClick={() => setEditing(plan)}>
                                        <Pencil />
                                        Editar
                                    </Button>
                                    {plan.is_active ? (
                                        <Button variant="ghost" size="sm" className="text-error hover:text-error" onClick={() => setDeactivating(plan)}>
                                            <PowerOff />
                                            Desactivar
                                        </Button>
                                    ) : (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            loading={setActive.isPending && setActive.variables?.id === plan.id}
                                            onClick={() => setActive.mutate({ id: plan.id, active: true })}
                                        >
                                            <Power />
                                            Activar
                                        </Button>
                                    )}
                                </div>
                            )}
                        </div>
                    )}
                />
            )}

            {isCreating && <PlanDialog onClose={() => setIsCreating(false)} />}
            {editing && <PlanDialog plan={editing} onClose={() => setEditing(null)} />}

            <ConfirmDialog
                open={deactivating !== null}
                title={`Desactivar ${deactivating?.name ?? 'plan'}`}
                description={
                    deactivating?.clients_count
                        ? `Ya no se ofrecerá en nuevas membresías. Los ${deactivating.clients_count} cliente(s) que lo tienen lo conservan hasta que cambien de plan.`
                        : 'Ya no se ofrecerá en nuevas membresías ni a los clientes. Puedes volver a activarlo cuando quieras.'
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
