import { zodResolver } from '@hookform/resolvers/zod';
import { XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { z } from 'zod';
import { planPrice } from '@/components/shared/PlanCards';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useCreateClient, useUpdateClient } from '@/hooks/useClients';
import { usePlans } from '@/hooks/useMemberships';
import { applyServerErrors } from '@/lib/forms';
import type { Client, TenantType } from '@/types';
import { CLIENT_TYPE_LABELS, DOCUMENT_LABELS, DOCUMENTS_FOR } from './clientLabels';

const optional = z
    .string()
    .trim()
    .transform((value) => (value === '' ? null : value));

const schema = z.object({
    name: z.string().trim().min(1, 'Ingresa el nombre').max(160),
    document_type: z.enum(['ruc', 'dni', 'ce']),
    document_number: z.string().trim().min(1, 'Ingresa el número de documento').max(20),
    contact_email: optional.pipe(z.string().email('Correo inválido').nullable()),
    contact_phone: optional,
    address: optional,
    admin_name: z.string().trim(),
    admin_email: z.string().trim(),
    plan: z.string(),
});

type FormInput = z.input<typeof schema>;
type FormOutput = z.output<typeof schema>;

const FIELDS = ['name', 'document_type', 'document_number', 'contact_email', 'contact_phone', 'address', 'admin_name', 'admin_email', 'plan'] as const;

/** Alta (con administrador inicial) o edición del perfil de un cliente. */
export default function ClientFormDialog({
    open,
    type,
    client,
    onClose,
    onCreated,
}: {
    open: boolean;
    type: TenantType;
    client?: Client;
    onClose: () => void;
    onCreated?: (client: Client) => void;
}) {
    const isEditing = client !== undefined;
    const createClient = useCreateClient();
    const updateClient = useUpdateClient(client?.id ?? 0);
    const mutation = isEditing ? updateClient : createClient;
    const [generalError, setGeneralError] = useState<string | null>(null);
    const labels = CLIENT_TYPE_LABELS[type];
    const { data: plans } = usePlans();
    const activePlans = (plans ?? []).filter((plan) => plan.is_active);

    const {
        register,
        control,
        handleSubmit,
        reset,
        setError,
        formState: { errors },
    } = useForm<FormInput, unknown, FormOutput>({
        resolver: zodResolver(
            isEditing
                ? schema
                : schema.extend({
                      admin_name: z.string().trim().min(1, 'Ingresa el nombre del administrador').max(120),
                      admin_email: z.string().trim().min(1, 'Ingresa el correo').email('Correo inválido'),
                  }),
        ),
    });

    useEffect(() => {
        if (!open) return;
        reset({
            name: client?.name ?? '',
            document_type: client?.document_type ?? DOCUMENTS_FOR[type][0],
            document_number: client?.document_number ?? '',
            contact_email: client?.contact_email ?? '',
            contact_phone: client?.contact_phone ?? '',
            address: client?.address ?? '',
            admin_name: '',
            admin_email: '',
            plan: activePlans[0]?.key ?? '',
        });
        // activePlans cambia de identidad en cada render; basta con la primera clave.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, client, type, reset, activePlans[0]?.key]);

    const close = () => {
        setGeneralError(null);
        onClose();
    };

    const onSubmit = handleSubmit((values) => {
        setGeneralError(null);
        const onError = (error: unknown) => setGeneralError(applyServerErrors(error, setError, FIELDS));
        const profile = {
            name: values.name,
            document_type: values.document_type,
            document_number: values.document_number,
            contact_email: values.contact_email,
            contact_phone: values.contact_phone,
            address: values.address,
        };

        if (isEditing) {
            updateClient.mutate(profile, { onSuccess: close, onError });
        } else {
            createClient.mutate(
                { ...profile, type, admin_name: values.admin_name, admin_email: values.admin_email, plan: values.plan },
                {
                    onSuccess: (created) => {
                        close();
                        onCreated?.(created);
                    },
                    onError,
                },
            );
        }
    });

    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && close()}>
            <DialogContent className="max-h-[90vh] max-w-xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{isEditing ? `Editar ${labels.singular.toLowerCase()}` : `Nueva ${labels.singular.toLowerCase()}`}</DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'El tipo de cliente no cambia: define sus documentos y su facturación.'
                            : 'El administrador recibirá un correo para crear su contraseña. Nadie más la conoce.'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={onSubmit} className="space-y-4" noValidate>
                    {generalError && (
                        <Alert variant="error">
                            <XCircle />
                            <AlertTitle>{generalError}</AlertTitle>
                        </Alert>
                    )}

                    <Field
                        label={type === 'company' ? 'Razón social' : 'Nombres y apellidos'}
                        htmlFor="client-name"
                        error={errors.name?.message}
                    >
                        <Input id="client-name" aria-invalid={Boolean(errors.name)} {...register('name')} />
                    </Field>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-[minmax(0,10rem)_1fr]">
                        <Field label="Documento" htmlFor="client-document-type" error={errors.document_type?.message}>
                            <Controller
                                control={control}
                                name="document_type"
                                render={({ field }) => (
                                    <Select value={field.value} onValueChange={(value) => value && field.onChange(value)} disabled={DOCUMENTS_FOR[type].length === 1}>
                                        <SelectTrigger id="client-document-type" className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {DOCUMENTS_FOR[type].map((document) => (
                                                <SelectItem key={document} value={document}>
                                                    {DOCUMENT_LABELS[document]}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                            />
                        </Field>
                        <Field label="Número" htmlFor="client-document-number" error={errors.document_number?.message}>
                            <Input
                                id="client-document-number"
                                inputMode="numeric"
                                placeholder={type === 'company' ? '20XXXXXXXXX' : '12345678'}
                                aria-invalid={Boolean(errors.document_number)}
                                {...register('document_number')}
                            />
                        </Field>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Field label="Correo de contacto" htmlFor="client-contact-email" error={errors.contact_email?.message}>
                            <Input id="client-contact-email" type="email" {...register('contact_email')} />
                        </Field>
                        <Field label="Teléfono" htmlFor="client-contact-phone" error={errors.contact_phone?.message}>
                            <Input id="client-contact-phone" placeholder="+51 987 654 321" {...register('contact_phone')} />
                        </Field>
                    </div>

                    <Field label="Dirección" htmlFor="client-address" error={errors.address?.message}>
                        <Input id="client-address" {...register('address')} />
                    </Field>

                    {!isEditing && (
                        <Field label="Plan" htmlFor="client-plan" hint="Nace con la membresía de este plan (precio y cuotas del catálogo)." error={errors.plan?.message}>
                            <Controller
                                control={control}
                                name="plan"
                                render={({ field }) => (
                                    <Select value={field.value} onValueChange={(value) => value && field.onChange(value)}>
                                        <SelectTrigger id="client-plan" className="w-full">
                                            <SelectValue placeholder="Elige el plan" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {activePlans.map((plan) => (
                                                <SelectItem key={plan.key} value={plan.key}>
                                                    {plan.name} · {planPrice(plan)}
                                                    {plan.monthly_price !== null && ' / mes'}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                            />
                        </Field>
                    )}

                    {!isEditing && (
                        <fieldset className="space-y-4 rounded-lg border p-4">
                            <legend className="px-1 text-sm font-semibold">Administrador de la cuenta</legend>
                            <Field label="Nombre" htmlFor="client-admin-name" error={errors.admin_name?.message}>
                                <Input id="client-admin-name" aria-invalid={Boolean(errors.admin_name)} {...register('admin_name')} />
                            </Field>
                            <Field label="Correo" htmlFor="client-admin-email" error={errors.admin_email?.message} hint="Aquí llegará la invitación.">
                                <Input
                                    id="client-admin-email"
                                    type="email"
                                    aria-invalid={Boolean(errors.admin_email)}
                                    {...register('admin_email')}
                                />
                            </Field>
                        </fieldset>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={close}>
                            Cancelar
                        </Button>
                        <Button type="submit" loading={mutation.isPending}>
                            {isEditing ? 'Guardar cambios' : 'Crear y enviar invitación'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
