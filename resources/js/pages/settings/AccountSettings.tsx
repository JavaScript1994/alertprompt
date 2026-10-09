import { zodResolver } from '@hookform/resolvers/zod';
import { CheckCircle2, XCircle } from 'lucide-react';
import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { z } from 'zod';
import PageHeader from '@/components/shared/PageHeader';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { useCan } from '@/hooks/usePermissions';
import { useAccountSettings, useUpdateAccountSettings } from '@/hooks/useUsers';
import { applyServerErrors } from '@/lib/forms';

const TIMEZONES = ['America/Lima', 'America/Bogota', 'America/Mexico_City', 'America/Santiago', 'America/Argentina/Buenos_Aires'];

const optional = z
    .string()
    .trim()
    .transform((value) => (value === '' ? null : value));

const schema = z.object({
    name: z.string().trim().min(1, 'Ingresa el nombre').max(160),
    contact_email: optional.pipe(z.string().email('Correo inválido').nullable()),
    contact_phone: optional,
    address: optional,
    timezone: z.string().min(1),
});

type FormInput = z.input<typeof schema>;
type FormOutput = z.output<typeof schema>;

const DOCUMENT_LABELS = { ruc: 'RUC', dni: 'DNI', ce: 'CE' } as const;

export default function AccountSettings() {
    const { data: account, isLoading } = useAccountSettings();
    const update = useUpdateAccountSettings();
    const canManage = useCan()('settings.manage');
    const [generalError, setGeneralError] = useState<string | null>(null);

    // `values` (no reset en un efecto): el formulario nace con los datos y el
    // Select de Radix nunca se monta vacío.
    const {
        register,
        control,
        handleSubmit,
        setError,
        formState: { errors, isDirty },
    } = useForm<FormInput, unknown, FormOutput>({
        resolver: zodResolver(schema),
        values: account
            ? {
                  name: account.name,
                  contact_email: account.contact_email ?? '',
                  contact_phone: account.contact_phone ?? '',
                  address: account.address ?? '',
                  timezone: account.timezone,
              }
            : undefined,
    });

    const onSubmit = handleSubmit((values) => {
        setGeneralError(null);
        update.mutate(values, {
            onError: (error) =>
                setGeneralError(applyServerErrors(error, setError, ['name', 'contact_email', 'contact_phone', 'address', 'timezone'])),
        });
    });

    if (isLoading || !account) return <Skeleton className="h-96 w-full rounded-xl" />;

    return (
        <div>
            <PageHeader title="Panel" description="Datos de tu cuenta en AlertPrompt." />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-12">
                <Card className="xl:col-span-8">
                    <CardHeader>
                        <CardTitle>Datos de la cuenta</CardTitle>
                        <CardDescription>Se usan en comprobantes y comunicaciones de AlertPrompt.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={onSubmit} className="space-y-4" noValidate>
                            {generalError && (
                                <Alert variant="error">
                                    <XCircle />
                                    <AlertTitle>{generalError}</AlertTitle>
                                </Alert>
                            )}
                            {update.isSuccess && !isDirty && (
                                <Alert variant="success">
                                    <CheckCircle2 />
                                    <AlertTitle>Cambios guardados.</AlertTitle>
                                </Alert>
                            )}

                            <Field label={account.type === 'company' ? 'Razón social' : 'Nombre'} htmlFor="account-name" error={errors.name?.message}>
                                <Input id="account-name" disabled={!canManage} {...register('name')} />
                            </Field>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Field label="Correo de contacto" htmlFor="account-email" error={errors.contact_email?.message}>
                                    <Input id="account-email" type="email" disabled={!canManage} {...register('contact_email')} />
                                </Field>
                                <Field label="Teléfono" htmlFor="account-phone" error={errors.contact_phone?.message}>
                                    <Input id="account-phone" disabled={!canManage} {...register('contact_phone')} />
                                </Field>
                            </div>
                            <Field label="Dirección" htmlFor="account-address" error={errors.address?.message}>
                                <Input id="account-address" disabled={!canManage} {...register('address')} />
                            </Field>
                            <Field
                                label="Zona horaria"
                                htmlFor="account-timezone"
                                error={errors.timezone?.message}
                                hint="Se usa para programar campañas y mostrar fechas."
                            >
                                <Controller
                                    control={control}
                                    name="timezone"
                                    render={({ field }) => (
                                        <Select value={field.value} onValueChange={(value) => value && field.onChange(value)} disabled={!canManage}>
                                            <SelectTrigger id="account-timezone" className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {[...new Set([...TIMEZONES, account.timezone])].map((zone) => (
                                                    <SelectItem key={zone} value={zone}>
                                                        {zone.replace(/_/g, ' ')}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    )}
                                />
                            </Field>

                            {canManage && (
                                <Button type="submit" loading={update.isPending} disabled={!isDirty}>
                                    Guardar cambios
                                </Button>
                            )}
                        </form>
                    </CardContent>
                </Card>

                <Card className="h-fit xl:col-span-4">
                    <CardHeader>
                        <CardTitle>Identificación</CardTitle>
                        <CardDescription>Para cambiarla, escribe a soporte de AlertPrompt.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm">
                        <div>
                            <p className="text-xs text-muted-foreground">Tipo</p>
                            <p className="font-medium">{account.type === 'company' ? 'Empresa' : 'Persona natural'}</p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">Documento</p>
                            <p className="font-medium">
                                {account.document_type ? `${DOCUMENT_LABELS[account.document_type]} ${account.document_number}` : '—'}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">Plan</p>
                            <p className="font-medium capitalize">{account.plan}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
