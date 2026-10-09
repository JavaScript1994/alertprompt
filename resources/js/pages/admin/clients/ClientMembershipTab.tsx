import { Ban, Plus, XCircle } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { MembershipStatusBadge, UsageMeters, formatDate, formatPrice } from '@/components/shared/MembershipBits';
import ConfirmDialog from '@/components/shared/ConfirmDialog';
import EmptyState from '@/components/shared/EmptyState';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { useCancelMembership, useClientMemberships, useCreateMembership } from '@/hooks/useMemberships';
import { useCan } from '@/hooks/usePermissions';
import { apiErrorMessage, isoDate } from '@/lib/format';
import type { Membership } from '@/types';

const PLANS = ['starter', 'growth', 'scale', 'enterprise'] as const;

function addCycle(start: string, cycle: string): string {
    const [y, m, d] = start.split('-').map(Number);
    const date = new Date(y, m - 1, d);
    if (cycle === 'yearly') date.setFullYear(date.getFullYear() + 1);
    else date.setMonth(date.getMonth() + 1);
    date.setDate(date.getDate() - 1);
    return isoDate(date);
}

const quota = (value: string) => (value.trim() === '' ? null : Number(value));

function NewMembershipDialog({ clientId, open, onClose }: { clientId: number; open: boolean; onClose: () => void }) {
    const create = useCreateMembership(clientId);
    const [form, setForm] = useState(() => {
        const today = isoDate(new Date());
        return {
            plan: 'starter',
            billing_cycle: 'monthly',
            price: '',
            starts_at: today,
            ends_at: addCycle(today, 'monthly'),
            whatsapp: '',
            sms: '',
            email: '',
            contract_reference: '',
            notes: '',
        };
    });

    const set = (key: keyof typeof form, value: string) =>
        setForm((current) => {
            const next = { ...current, [key]: value };
            // Al cambiar inicio o ciclo, el fin se recalcula a un ciclo completo.
            if (key === 'starts_at' || key === 'billing_cycle') next.ends_at = addCycle(next.starts_at, next.billing_cycle);
            return next;
        });

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();
        create.mutate(
            {
                plan: form.plan,
                billing_cycle: form.billing_cycle,
                price: form.price,
                starts_at: form.starts_at,
                ends_at: form.ends_at,
                quotas: { whatsapp: quota(form.whatsapp), sms: quota(form.sms), email: quota(form.email) },
                contract_reference: form.contract_reference || null,
                notes: form.notes || null,
            },
            { onSuccess: onClose },
        );
    };

    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent className="max-h-[90vh] max-w-xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Nueva membresía</DialogTitle>
                    <DialogDescription>
                        Si empieza hoy o antes, reemplaza a la vigente y el cliente pasa al nuevo plan. Si empieza después,
                        queda programada.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={onSubmit} className="space-y-4" noValidate>
                    {create.isError && (
                        <Alert variant="error">
                            <XCircle />
                            <AlertTitle>
                                {apiErrorMessage(create.error, ['starts_at', 'ends_at', 'price', 'plan', 'billing_cycle'], 'No se pudo guardar la membresía.')}
                            </AlertTitle>
                        </Alert>
                    )}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <Field label="Plan" htmlFor="m-plan">
                            <Select value={form.plan} onValueChange={(v) => v && set('plan', v)}>
                                <SelectTrigger id="m-plan" className="w-full capitalize">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {PLANS.map((plan) => (
                                        <SelectItem key={plan} value={plan} className="capitalize">
                                            {plan}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field label="Ciclo" htmlFor="m-cycle">
                            <Select value={form.billing_cycle} onValueChange={(v) => v && set('billing_cycle', v)}>
                                <SelectTrigger id="m-cycle" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="monthly">Mensual</SelectItem>
                                    <SelectItem value="yearly">Anual</SelectItem>
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field label="Precio (S/ sin IGV)" htmlFor="m-price">
                            <Input id="m-price" type="number" min="0" step="0.01" value={form.price} onChange={(e) => set('price', e.target.value)} />
                        </Field>
                        <Field label="Inicio" htmlFor="m-start">
                            <Input id="m-start" type="date" value={form.starts_at} onChange={(e) => set('starts_at', e.target.value)} />
                        </Field>
                        <Field label="Fin" htmlFor="m-end">
                            <Input id="m-end" type="date" value={form.ends_at} onChange={(e) => set('ends_at', e.target.value)} />
                        </Field>
                        <Field label="Referencia" htmlFor="m-ref">
                            <Input id="m-ref" placeholder="CTR-2026-001" value={form.contract_reference} onChange={(e) => set('contract_reference', e.target.value)} />
                        </Field>
                    </div>
                    <fieldset className="grid grid-cols-1 gap-4 rounded-lg border p-4 sm:grid-cols-3">
                        <legend className="px-1 text-sm font-semibold">Cuota mensual (vacío = sin límite)</legend>
                        <Field label="WhatsApp" htmlFor="m-q-wa">
                            <Input id="m-q-wa" type="number" min="0" value={form.whatsapp} onChange={(e) => set('whatsapp', e.target.value)} />
                        </Field>
                        <Field label="SMS" htmlFor="m-q-sms">
                            <Input id="m-q-sms" type="number" min="0" value={form.sms} onChange={(e) => set('sms', e.target.value)} />
                        </Field>
                        <Field label="Email" htmlFor="m-q-email">
                            <Input id="m-q-email" type="number" min="0" value={form.email} onChange={(e) => set('email', e.target.value)} />
                        </Field>
                    </fieldset>
                    <Field label="Notas internas" htmlFor="m-notes">
                        <Textarea id="m-notes" rows={2} value={form.notes} onChange={(e) => set('notes', e.target.value)} />
                    </Field>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            Cancelar
                        </Button>
                        <Button type="submit" loading={create.isPending} disabled={form.price === ''}>
                            Guardar membresía
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function ClientMembershipTab({ clientId }: { clientId: number }) {
    const { data, isLoading } = useClientMemberships(clientId);
    const cancel = useCancelMembership(clientId);
    const canManage = useCan()('admin.memberships.manage');
    const [isCreating, setIsCreating] = useState(false);
    const [cancelling, setCancelling] = useState<Membership | null>(null);
    const [reason, setReason] = useState('');

    if (isLoading || !data) return <Skeleton className="h-64 w-full rounded-xl" />;

    const current = data.data.find((m) => m.status === 'active');

    return (
        <div className="space-y-6">
            <div className="flex justify-end">
                {canManage && (
                    <Button onClick={() => setIsCreating(true)}>
                        <Plus />
                        {current ? 'Renovar o cambiar plan' : 'Nueva membresía'}
                    </Button>
                )}
            </div>

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Consumo de este mes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <UsageMeters usage={data.usage} />
                    </CardContent>
                </Card>

                {data.data.length === 0 ? (
                    <EmptyState icon={Plus} title="Sin membresías" description="Registra el contrato del cliente para controlar su plan y cuotas." />
                ) : (
                    <Card className="gap-0 p-0">
                        <CardHeader className="px-6 py-4">
                            <CardTitle>Contratos</CardTitle>
                        </CardHeader>
                        <div className="divide-y border-t">
                            {data.data.map((membership) => (
                                <div key={membership.id} className="flex flex-wrap items-start justify-between gap-3 px-6 py-4 text-sm">
                                    <div className="space-y-0.5">
                                        <p className="flex items-center gap-2 font-medium capitalize">
                                            Plan {membership.plan} <MembershipStatusBadge status={membership.status} />
                                        </p>
                                        <p className="text-muted-foreground">
                                            {formatDate(membership.starts_at)} – {formatDate(membership.ends_at)} · {formatPrice(membership)}
                                        </p>
                                        {membership.contract_reference && <p className="text-xs text-muted-foreground">{membership.contract_reference}</p>}
                                        {membership.cancel_reason && <p className="text-xs text-error">Cancelada: {membership.cancel_reason}</p>}
                                        {membership.notes && <p className="text-xs text-muted-foreground italic">{membership.notes}</p>}
                                    </div>
                                    {canManage && (membership.status === 'active' || membership.status === 'scheduled') && (
                                        <Button variant="ghost" size="sm" className="text-error hover:text-error" onClick={() => setCancelling(membership)}>
                                            <Ban />
                                            Cancelar
                                        </Button>
                                    )}
                                </div>
                            ))}
                        </div>
                    </Card>
                )}
            </div>

            {isCreating && <NewMembershipDialog clientId={clientId} open onClose={() => setIsCreating(false)} />}

            <ConfirmDialog
                open={cancelling !== null}
                title="Cancelar membresía"
                description="El cliente se queda sin esta membresía. No se suspende automáticamente: decide aparte si suspenderlo."
                confirmLabel="Cancelar membresía"
                destructive
                loading={cancel.isPending}
                onConfirm={() =>
                    cancelling &&
                    cancel.mutate(
                        { id: cancelling.id, reason: reason || undefined },
                        {
                            onSuccess: () => {
                                setCancelling(null);
                                setReason('');
                            },
                        },
                    )
                }
                onCancel={() => setCancelling(null)}
            >
                <Field label="Motivo (opcional)" htmlFor="m-cancel-reason">
                    <Input id="m-cancel-reason" value={reason} onChange={(event) => setReason(event.target.value)} />
                </Field>
            </ConfirmDialog>
        </div>
    );
}
