import { Pencil, XCircle } from 'lucide-react';
import { useState, type FormEvent } from 'react';
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
import { usePlans, useUpdatePlan } from '@/hooks/useMemberships';
import { useCan } from '@/hooks/usePermissions';
import { apiErrorMessage } from '@/lib/format';
import type { Plan } from '@/types';

const toNumber = (value: string) => (value.trim() === '' ? null : Number(value));

function EditPlanDialog({ plan, onClose }: { plan: Plan; onClose: () => void }) {
    const update = useUpdatePlan();
    const [form, setForm] = useState({
        name: plan.name,
        description: plan.description ?? '',
        monthly_price: plan.monthly_price ?? '',
        whatsapp: plan.quotas.whatsapp?.toString() ?? '',
        sms: plan.quotas.sms?.toString() ?? '',
        email: plan.quotas.email?.toString() ?? '',
        is_public: plan.is_public,
    });
    const set = (key: keyof typeof form, value: string | boolean) => setForm((current) => ({ ...current, [key]: value }));

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();
        update.mutate(
            {
                id: plan.id,
                name: form.name,
                description: form.description || null,
                monthly_price: form.monthly_price === '' ? null : form.monthly_price,
                quotas: { whatsapp: toNumber(form.whatsapp), sms: toNumber(form.sms), email: toNumber(form.email) },
                is_public: form.is_public,
            },
            { onSuccess: onClose },
        );
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Editar plan {plan.name}</DialogTitle>
                    <DialogDescription>
                        Los cambios aplican a las membresías que se creen desde ahora; las vigentes conservan su precio y
                        cuotas.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={onSubmit} className="space-y-4">
                    {update.isError && (
                        <Alert variant="error">
                            <XCircle />
                            <AlertTitle>{apiErrorMessage(update.error, ['name', 'monthly_price', 'quotas'], 'No se pudo guardar el plan.')}</AlertTitle>
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
                        <Button type="submit" loading={update.isPending} disabled={!form.name.trim()}>
                            Guardar plan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Plans() {
    const { data: plans, isLoading } = usePlans();
    const canManage = useCan()('admin.memberships.manage');
    const [editing, setEditing] = useState<Plan | null>(null);

    return (
        <div>
            <PageHeader
                title="Planes"
                description="Catálogo que ven los clientes en Membresía y que se propone al crear una membresía."
            />

            <Alert variant="info" className="mb-6">
                <AlertDescription>
                    Precios y cantidades de ejemplo: ajústalos a tus valores reales. Los planes con borde punteado no se
                    muestran a los clientes (solo a quien ya lo tiene).
                </AlertDescription>
            </Alert>

            {isLoading || !plans ? (
                <Skeleton className="h-72 w-full rounded-xl" />
            ) : (
                <PlanCards
                    plans={plans}
                    footer={(plan) =>
                        canManage && (
                            <Button variant="outline" onClick={() => setEditing(plan)}>
                                <Pencil />
                                Editar
                            </Button>
                        )
                    }
                />
            )}

            {editing && <EditPlanDialog plan={editing} onClose={() => setEditing(null)} />}
        </div>
    );
}
