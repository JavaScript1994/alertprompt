import { zodResolver } from '@hookform/resolvers/zod';
import { CheckCircle2, Info, MessageCircle, Smartphone, TriangleAlert, XCircle } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { ACCOUNT_CHANNEL_LABELS, ChannelAccountStatusBadge, QualityBadge } from '@/components/shared/ChannelAccountBadges';
import PageHeader from '@/components/shared/PageHeader';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { useChannelAccounts, useRequestChannelAccount } from '@/hooks/useChannelAccounts';
import { useCan } from '@/hooks/usePermissions';
import { applyServerErrors } from '@/lib/forms';
import type { ChannelAccountSummary } from '@/types';

const schema = z.object({
    sender: z
        .string()
        .trim()
        .regex(/^\+[1-9][\d\s-]{7,18}$/, 'Usa el formato internacional, por ejemplo +51987654321'),
    display_name: z.string().trim().max(80),
});

type FormValues = z.infer<typeof schema>;

function RequestForm({ summary, onDone }: { summary: ChannelAccountSummary; onDone: () => void }) {
    const request = useRequestChannelAccount();
    const [error, setError] = useState<string | null>(null);
    const {
        register,
        handleSubmit,
        setError: setFieldError,
        formState: { errors },
    } = useForm<FormValues>({
        resolver: zodResolver(schema),
        defaultValues: { sender: summary.account?.sender ?? '', display_name: summary.account?.display_name ?? '' },
    });

    const onSubmit = handleSubmit((values) =>
        request.mutate(
            { channel: summary.channel, sender: values.sender, display_name: values.display_name || null },
            { onSuccess: onDone, onError: (e) => setError(applyServerErrors(e, setFieldError, ['sender', 'display_name'])) },
        ),
    );

    return (
        <form onSubmit={onSubmit} className="mt-4 space-y-4 border-t pt-4" noValidate>
            {error && (
                <Alert variant="error">
                    <XCircle />
                    <AlertTitle>{error}</AlertTitle>
                </Alert>
            )}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Field label="Número" htmlFor={`sender-${summary.channel}`} error={errors.sender?.message}>
                    <Input id={`sender-${summary.channel}`} placeholder="+51987654321" {...register('sender')} />
                </Field>
                <Field
                    label="Nombre visible"
                    htmlFor={`display-${summary.channel}`}
                    error={errors.display_name?.message}
                    hint={summary.channel === 'whatsapp' ? 'Como aparecerá tu negocio en WhatsApp.' : undefined}
                >
                    <Input id={`display-${summary.channel}`} {...register('display_name')} />
                </Field>
            </div>
            {summary.account?.status === 'active' && (
                <Alert variant="warning">
                    <TriangleAlert />
                    <AlertDescription>
                        Cambiar el número lo deja pendiente hasta que AlertPrompt lo active. Mientras tanto no se envía
                        con él.
                    </AlertDescription>
                </Alert>
            )}
            <div className="flex gap-2">
                <Button type="submit" loading={request.isPending}>
                    Enviar solicitud
                </Button>
                <Button type="button" variant="outline" onClick={onDone}>
                    Cancelar
                </Button>
            </div>
        </form>
    );
}

function ChannelCard({ summary, canManage }: { summary: ChannelAccountSummary; canManage: boolean }) {
    const [editing, setEditing] = useState(false);
    const { account } = summary;
    const Icon = summary.channel === 'whatsapp' ? MessageCircle : Smartphone;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Icon className="size-5 text-primary dark:text-brand-200" />
                    {ACCOUNT_CHANNEL_LABELS[summary.channel]}
                </CardTitle>
                <CardDescription>
                    {account
                        ? `${account.sender}${account.display_name ? ` · ${account.display_name}` : ''}`
                        : 'Sin número propio conectado.'}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div className="flex flex-wrap gap-2">
                    {account && <ChannelAccountStatusBadge status={account.status} />}
                    {account && <QualityBadge rating={account.quality_rating} />}
                    {account?.messaging_tier && (
                        <span className="text-xs text-muted-foreground">Límite diario: {account.messaging_tier}</span>
                    )}
                </div>

                {account?.status !== 'active' &&
                    (summary.shared_sender_allowed ? (
                        <Alert variant="info" className="mt-4">
                            <Info />
                            <AlertDescription>
                                Mientras no tengas un número propio activo, tus campañas salen con el número compartido de
                                AlertPrompt (solo recomendado para pruebas).
                            </AlertDescription>
                        </Alert>
                    ) : (
                        <Alert variant="warning" className="mt-4">
                            <TriangleAlert />
                            <AlertDescription>
                                Necesitas un número propio activo para enviar campañas por{' '}
                                {ACCOUNT_CHANNEL_LABELS[summary.channel]}.
                            </AlertDescription>
                        </Alert>
                    ))}

                {account?.status === 'pending' && (
                    <p className="mt-3 text-sm text-muted-foreground">
                        Recibimos tu solicitud. El equipo de AlertPrompt da de alta el número con el proveedor y te avisa
                        cuando quede activo.
                    </p>
                )}

                {canManage && !editing && (
                    <Button variant="outline" className="mt-4" onClick={() => setEditing(true)}>
                        {account ? 'Cambiar número' : 'Conectar mi número'}
                    </Button>
                )}
                {editing && <RequestForm summary={summary} onDone={() => setEditing(false)} />}
            </CardContent>
        </Card>
    );
}

export default function ChannelAccounts() {
    const { data, isLoading } = useChannelAccounts();
    const canManage = useCan()('whatsapp_account.manage');

    return (
        <div>
            <PageHeader
                title="Cuenta de WhatsApp"
                description="Números propios con los que salen tus campañas de WhatsApp y SMS."
            />

            <Alert variant="primary" className="mb-6">
                <CheckCircle2 />
                <AlertDescription>
                    Con tu propio número, la calidad y el límite diario de WhatsApp son tuyos: Meta los mide por número.
                    Mantener la calidad en verde (pocos bloqueos y reportes) es lo que permite subir de límite.
                </AlertDescription>
            </Alert>

            {isLoading || !data ? (
                <Skeleton className="h-64 w-full rounded-xl" />
            ) : (
                <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    {data.map((summary) => (
                        <ChannelCard key={summary.channel} summary={summary} canManage={canManage} />
                    ))}
                </div>
            )}
        </div>
    );
}
