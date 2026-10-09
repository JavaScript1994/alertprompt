import { CheckCircle2, Copy, XCircle } from 'lucide-react';
import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { ACCOUNT_CHANNEL_LABELS, ChannelAccountStatusBadge } from '@/components/shared/ChannelAccountBadges';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { useClientChannelAccounts, useConfigureChannelAccount } from '@/hooks/useChannelAccounts';
import { useCan } from '@/hooks/usePermissions';
import { applyServerErrors } from '@/lib/forms';
import type { AdminChannelAccountSlot } from '@/types';

/** Credenciales que pide cada proveedor (claves de config/channels.php). */
const CREDENTIAL_FIELDS: Record<string, { key: string; label: string }[]> = {
    twilio: [
        { key: 'account_sid', label: 'Account SID de la subcuenta' },
        { key: 'auth_token', label: 'Auth token de la subcuenta' },
    ],
    cloud: [
        { key: 'phone_number_id', label: 'Phone number ID' },
        { key: 'waba_id', label: 'WhatsApp Business Account ID' },
        { key: 'access_token', label: 'Access token' },
    ],
};

const PROVIDER_LABELS: Record<string, string> = { twilio: 'Twilio', cloud: 'Meta Cloud API' };

interface FormValues {
    provider: string;
    sender: string;
    display_name: string;
    status: string;
    quality_rating: string;
    messaging_tier: string;
    notes: string;
    credentials: Record<string, string>;
}

const FIELDS = ['provider', 'sender', 'display_name', 'status', 'quality_rating', 'messaging_tier', 'notes'] as const;

function ChannelForm({ clientId, slot, canManage }: { clientId: number; slot: AdminChannelAccountSlot; canManage: boolean }) {
    const configure = useConfigureChannelAccount(clientId, slot.channel);
    const [error, setError] = useState<string | null>(null);
    const [copied, setCopied] = useState(false);
    const account = slot.account;

    const {
        register,
        control,
        handleSubmit,
        watch,
        reset,
        setError: setFieldError,
        formState: { errors, isDirty },
    } = useForm<FormValues>({
        values: {
            provider: account?.provider ?? slot.providers[0] ?? '',
            sender: account?.sender ?? '',
            display_name: account?.display_name ?? '',
            status: account?.status ?? 'pending',
            quality_rating: account?.quality_rating ?? '',
            messaging_tier: account?.messaging_tier ?? '',
            notes: account?.notes ?? '',
            credentials: {},
        },
    });

    const provider = watch('provider');

    const onSubmit = handleSubmit((values) => {
        setError(null);
        configure.mutate(
            {
                provider: values.provider,
                sender: values.sender,
                display_name: values.display_name || null,
                status: values.status,
                quality_rating: values.quality_rating || null,
                messaging_tier: values.messaging_tier || null,
                notes: values.notes || null,
                // Vacíos no se envían: el backend conserva las guardadas.
                credentials: Object.fromEntries(Object.entries(values.credentials ?? {}).filter(([, v]) => v)),
            },
            {
                onSuccess: () => reset(undefined, { keepValues: true }),
                onError: (e) => setError(applyServerErrors(e, setFieldError, FIELDS)),
            },
        );
    });

    const copyWebhook = async () => {
        if (!account?.webhook_url) return;
        await navigator.clipboard.writeText(account.webhook_url);
        setCopied(true);
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex flex-wrap items-center gap-2">
                    {ACCOUNT_CHANNEL_LABELS[slot.channel]}
                    {account && <ChannelAccountStatusBadge status={account.status} />}
                </CardTitle>
                <CardDescription>
                    {account ? 'Configura el número en el proveedor y actívalo.' : 'El cliente aún no pidió un número propio.'}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={onSubmit} className="space-y-4" noValidate>
                    {error && (
                        <Alert variant="error">
                            <XCircle />
                            <AlertTitle>{error}</AlertTitle>
                        </Alert>
                    )}
                    {configure.isSuccess && !isDirty && (
                        <Alert variant="success">
                            <CheckCircle2 />
                            <AlertTitle>Cuenta guardada.</AlertTitle>
                        </Alert>
                    )}

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Field label="Proveedor" htmlFor={`provider-${slot.channel}`} error={errors.provider?.message}>
                            <Controller
                                control={control}
                                name="provider"
                                render={({ field }) => (
                                    <Select value={field.value} onValueChange={(v) => v && field.onChange(v)} disabled={!canManage}>
                                        <SelectTrigger id={`provider-${slot.channel}`} className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {slot.providers.map((key) => (
                                                <SelectItem key={key} value={key}>
                                                    {PROVIDER_LABELS[key] ?? key}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                            />
                        </Field>
                        <Field label="Estado" htmlFor={`status-${slot.channel}`}>
                            <Controller
                                control={control}
                                name="status"
                                render={({ field }) => (
                                    <Select value={field.value} onValueChange={(v) => v && field.onChange(v)} disabled={!canManage}>
                                        <SelectTrigger id={`status-${slot.channel}`} className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="pending">Pendiente</SelectItem>
                                            <SelectItem value="active">Activa</SelectItem>
                                            <SelectItem value="disabled">Desactivada</SelectItem>
                                        </SelectContent>
                                    </Select>
                                )}
                            />
                        </Field>
                        <Field label="Número" htmlFor={`sender-${slot.channel}`} error={errors.sender?.message}>
                            <Input id={`sender-${slot.channel}`} placeholder="+51987654321" disabled={!canManage} {...register('sender')} />
                        </Field>
                        <Field label="Nombre visible" htmlFor={`display-${slot.channel}`}>
                            <Input id={`display-${slot.channel}`} disabled={!canManage} {...register('display_name')} />
                        </Field>
                        {slot.channel === 'whatsapp' && (
                            <>
                                <Field label="Calidad (Meta)" htmlFor={`quality-${slot.channel}`}>
                                    <Controller
                                        control={control}
                                        name="quality_rating"
                                        render={({ field }) => (
                                            <Select value={field.value} onValueChange={(v) => v && field.onChange(v)} disabled={!canManage}>
                                                <SelectTrigger id={`quality-${slot.channel}`} className="w-full">
                                                    <SelectValue placeholder="Sin datos" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="GREEN">Verde (alta)</SelectItem>
                                                    <SelectItem value="YELLOW">Amarilla (media)</SelectItem>
                                                    <SelectItem value="RED">Roja (baja)</SelectItem>
                                                    <SelectItem value="UNKNOWN">Sin datos</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        )}
                                    />
                                </Field>
                                <Field label="Límite diario (tier)" htmlFor={`tier-${slot.channel}`}>
                                    <Input id={`tier-${slot.channel}`} placeholder="TIER_1K" disabled={!canManage} {...register('messaging_tier')} />
                                </Field>
                            </>
                        )}
                    </div>

                    <fieldset className="space-y-3 rounded-lg border p-4">
                        <legend className="px-1 text-sm font-semibold">Credenciales de {PROVIDER_LABELS[provider] ?? provider}</legend>
                        <p className="text-xs text-muted-foreground">
                            Se guardan cifradas y nunca se muestran. Déjalas en blanco para conservar las actuales.
                            {provider === 'twilio' && ' Sin subcuenta propia, el número se envía con la cuenta principal de la plataforma.'}
                        </p>
                        {(CREDENTIAL_FIELDS[provider] ?? []).map(({ key, label }) => (
                            <Field key={key} label={label} htmlFor={`cred-${slot.channel}-${key}`}>
                                <Input
                                    id={`cred-${slot.channel}-${key}`}
                                    type="password"
                                    autoComplete="off"
                                    placeholder={account?.credential_hints?.[key] ?? 'Sin cargar'}
                                    disabled={!canManage}
                                    {...register(`credentials.${key}`)}
                                />
                            </Field>
                        ))}
                    </fieldset>

                    <Field label="Notas internas" htmlFor={`notes-${slot.channel}`}>
                        <Textarea id={`notes-${slot.channel}`} rows={2} disabled={!canManage} {...register('notes')} />
                    </Field>

                    {account?.webhook_url && (
                        <div className="rounded-lg bg-muted/60 p-3 text-xs">
                            <p className="font-medium text-foreground">URL de estados para el proveedor</p>
                            <div className="mt-1 flex items-center gap-2">
                                <code className="min-w-0 flex-1 truncate font-mono">{account.webhook_url}</code>
                                <Button type="button" variant="ghost" size="icon-sm" onClick={copyWebhook} aria-label="Copiar URL">
                                    {copied ? <CheckCircle2 /> : <Copy />}
                                </Button>
                            </div>
                        </div>
                    )}

                    {canManage && (
                        <Button type="submit" loading={configure.isPending} disabled={!isDirty}>
                            Guardar cuenta
                        </Button>
                    )}
                </form>
            </CardContent>
        </Card>
    );
}

export default function ClientChannelsTab({ clientId }: { clientId: number }) {
    const { data, isLoading } = useClientChannelAccounts(clientId);
    const canManage = useCan()('admin.clients.update');

    if (isLoading || !data) return <Skeleton className="h-64 w-full rounded-xl" />;

    return (
        <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
            {data.map((slot) => (
                <ChannelForm key={slot.channel} clientId={clientId} slot={slot} canManage={canManage} />
            ))}
        </div>
    );
}
