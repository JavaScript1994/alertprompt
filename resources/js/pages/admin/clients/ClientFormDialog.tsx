import { zodResolver } from '@hookform/resolvers/zod';
import { Building2, UserRound, XCircle } from 'lucide-react';
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
import PersonalDataFields, { PersonalDataNotice } from '@/features/profile/PersonalDataFields';
import PhotoField from '@/features/profile/PhotoField';
import { PERSONAL_KEYS, personalDataShape, type PersonalKey } from '@/features/profile/schemas';
import { useCreateClient, useUpdateClient } from '@/hooks/useClients';
import { usePlans } from '@/hooks/useMemberships';
import { applyServerErrors, serverFieldError } from '@/lib/forms';
import { cn } from '@/lib/utils';
import type { Client, TenantType } from '@/types';
import { CLIENT_TYPE_LABELS, DOCUMENT_LABELS, DOCUMENTS_FOR } from './clientLabels';

const optional = z
    .string()
    .trim()
    .transform((value) => (value === '' ? null : value));

const profileShape = {
    name: z.string().trim().min(1, 'Ingresa el nombre').max(160),
    document_type: z.enum(['ruc', 'dni', 'ce']),
    document_number: z.string().trim().min(1, 'Ingresa el número de documento').max(20),
    contact_email: optional.pipe(z.string().email('Correo inválido').nullable()),
    contact_phone: optional,
    address: optional,
};

const editSchema = z.object({
    ...profileShape,
    slug: z
        .string()
        .trim()
        .toLowerCase()
        .regex(/^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])$/, 'De 3 a 40 letras minúsculas, números o guiones'),
});

/** Alta: empresa + ficha del administrador (mismas reglas que Mi perfil, con prefijo admin_). */
const createSchema = z.object({
    ...profileShape,
    slug: z.string().optional(),
    plan: z.string().min(1, 'Elige el plan'),
    admin_email: z.string().trim().min(1, 'Ingresa el correo').email('Correo inválido'),
    admin_first_name: personalDataShape.first_name,
    admin_last_name: personalDataShape.last_name,
    admin_job_title: personalDataShape.job_title,
    admin_birth_date: personalDataShape.birth_date,
    admin_phone: personalDataShape.phone,
    admin_mobile: personalDataShape.mobile,
});

type FormInput = z.input<typeof createSchema>;
type FormOutput = z.output<typeof createSchema>;

const FIELDS = [
    'slug',
    'name',
    'document_type',
    'document_number',
    'contact_email',
    'contact_phone',
    'address',
    'plan',
    'admin_email',
    ...PERSONAL_KEYS.map((key) => `admin_${key}` as const),
] as const;

function SectionTitle({ icon: Icon, children }: { icon: typeof Building2; children: string }) {
    return (
        <h3 className="mb-4 flex items-center gap-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
            <Icon className="size-4" />
            {children}
        </h3>
    );
}

/**
 * Alta (con la ficha del administrador inicial) o edición del perfil de un
 * cliente. En el alta: modal ancho a dos columnas, Empresa | Administrador,
 * para revisar todo sin desplazarse; en pantallas angostas se apila.
 */
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
    const [photo, setPhoto] = useState<File | null>(null);
    const [photoKey, setPhotoKey] = useState(0);
    const labels = CLIENT_TYPE_LABELS[type];
    const { data: plans } = usePlans();
    const activePlans = (plans ?? []).filter((plan) => plan.is_active);

    const {
        register,
        control,
        handleSubmit,
        reset,
        watch,
        setError,
        formState: { errors },
    } = useForm<FormInput, unknown, FormOutput>({
        // En edición solo se valida (y se envía) el perfil del cliente; los
        // campos del administrador quedan vacíos y no se usan.
        resolver: zodResolver(isEditing ? (editSchema as unknown as typeof createSchema) : createSchema),
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
            slug: client?.slug ?? '',
            plan: activePlans[0]?.key ?? '',
            admin_email: '',
            admin_first_name: '',
            admin_last_name: '',
            admin_job_title: '',
            admin_birth_date: '',
            admin_phone: '',
            admin_mobile: '',
        });
        setPhoto(null);
        setPhotoKey((key) => key + 1);
        // activePlans cambia de identidad en cada render; basta con la primera clave.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, client, type, reset, activePlans[0]?.key]);

    const close = () => {
        setGeneralError(null);
        onClose();
    };

    const onSubmit = handleSubmit((values) => {
        setGeneralError(null);
        // La foto muestra su propio error junto al campo.
        const onError = (error: unknown) => {
            const unmatched = applyServerErrors(error, setError, FIELDS);
            setGeneralError(serverFieldError(error, 'admin_photo') === unmatched ? null : unmatched);
        };
        const profile = {
            name: values.name,
            document_type: values.document_type,
            document_number: values.document_number,
            contact_email: values.contact_email,
            contact_phone: values.contact_phone,
            address: values.address,
        };

        if (isEditing) {
            updateClient.mutate({ ...profile, ...(values.slug ? { slug: values.slug } : {}) }, { onSuccess: close, onError });
            return;
        }

        createClient.mutate(
            {
                ...profile,
                type,
                plan: values.plan,
                admin_email: values.admin_email,
                admin_first_name: values.admin_first_name,
                admin_last_name: values.admin_last_name,
                admin_job_title: values.admin_job_title,
                admin_birth_date: values.admin_birth_date,
                admin_phone: values.admin_phone,
                admin_mobile: values.admin_mobile,
                admin_photo: photo,
            },
            {
                onSuccess: (created) => {
                    close();
                    onCreated?.(created);
                },
                onError,
            },
        );
    });

    const adminName = `${watch('admin_first_name') ?? ''} ${watch('admin_last_name') ?? ''}`.trim();

    const clientFields = (
        <div className="space-y-4">
            <Field label={type === 'company' ? 'Razón social' : 'Nombres y apellidos'} htmlFor="client-name" error={errors.name?.message}>
                <Input id="client-name" aria-invalid={Boolean(errors.name)} {...register('name')} />
            </Field>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-[minmax(0,9rem)_1fr]">
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
        </div>
    );

    // ".alertprompt.pe" (o ".localhost:8000"): lo que sigue al subdominio en su URL actual.
    const domainSuffix = client?.login_url && client.slug ? new URL(client.login_url).host.slice(client.slug.length) : '';

    const slugField = isEditing && client?.slug !== null && (
        <Field
            label="Subdominio del login"
            htmlFor="client-slug"
            error={errors.slug?.message}
            hint="Cambiarlo invalida los enlaces que la empresa ya compartió."
        >
            <div className="flex items-center rounded-md border focus-within:border-primary focus-within:ring-[3px] focus-within:ring-ring/40">
                <Input id="client-slug" className="border-0 font-mono focus-visible:ring-0" aria-invalid={Boolean(errors.slug)} {...register('slug')} />
                <span className="shrink-0 pr-3 font-mono text-sm text-muted-foreground">{domainSuffix}</span>
            </div>
        </Field>
    );

    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && close()}>
            <DialogContent className={cn('max-h-[92vh] overflow-y-auto', isEditing ? 'max-w-xl' : 'max-w-5xl')}>
                <DialogHeader>
                    <DialogTitle>{isEditing ? `Editar ${labels.singular.toLowerCase()}` : `Nueva ${labels.singular.toLowerCase()}`}</DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'El tipo de cliente no cambia: define sus documentos y su facturación.'
                            : 'El administrador recibirá un correo para crear su contraseña. Nadie más la conoce.'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={onSubmit} className="space-y-5" noValidate>
                    {generalError && (
                        <Alert variant="error">
                            <XCircle />
                            <AlertTitle>{generalError}</AlertTitle>
                        </Alert>
                    )}

                    {isEditing ? (
                        <div className="space-y-4">
                            {clientFields}
                            {slugField}
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-x-8 gap-y-6 lg:grid-cols-2 lg:divide-x">
                            <section className="lg:pr-8">
                                <SectionTitle icon={Building2}>{type === 'company' ? 'Empresa' : 'Cliente'}</SectionTitle>
                                {clientFields}
                            </section>

                            <section className="space-y-4">
                                <SectionTitle icon={UserRound}>Administrador de la cuenta</SectionTitle>
                                <PhotoField
                                    key={photoKey}
                                    size="sm"
                                    name={adminName}
                                    photoUrl={null}
                                    onFileChange={setPhoto}
                                    error={serverFieldError(createClient.error, 'admin_photo')}
                                />
                                <PersonalDataFields
                                    idPrefix="client-admin"
                                    register={(key: PersonalKey) => register(`admin_${key}`)}
                                    errors={Object.fromEntries(PERSONAL_KEYS.map((key) => [key, errors[`admin_${key}`]?.message]))}
                                />
                                <Field label="Correo" htmlFor="client-admin-email" error={errors.admin_email?.message} hint="Será su usuario de inicio de sesión. Aquí llegará la invitación.">
                                    <Input id="client-admin-email" type="email" autoComplete="off" aria-invalid={Boolean(errors.admin_email)} {...register('admin_email')} />
                                </Field>
                            </section>
                        </div>
                    )}

                    <DialogFooter className="items-center border-t pt-4">
                        {!isEditing && (
                            <div className="sm:mr-auto sm:max-w-md">
                                <PersonalDataNotice />
                            </div>
                        )}
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
