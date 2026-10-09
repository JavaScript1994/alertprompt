import { zodResolver } from '@hookform/resolvers/zod';
import { ShieldCheck, ShieldOff, XCircle } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import ChannelBadge from '@/components/shared/ChannelBadge';
import ConfirmDialog from '@/components/shared/ConfirmDialog';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { useConsents, useGrantConsent, useRevokeConsent } from '@/hooks/useConsents';
import { apiErrorMessage, formatDateTime } from '@/lib/format';
import type { Consent, TemplateChannel } from '@/types';

const CHANNELS: TemplateChannel[] = ['whatsapp', 'sms', 'email'];

const consentSchema = z.object({
    source: z.string().min(1, 'Indicá el origen').max(255),
    evidence_text: z.string().min(1, 'Ingresá el texto exacto que aceptó el contacto').max(2000),
});

type ConsentFormValues = z.infer<typeof consentSchema>;

export default function ConsentsModal({
    contactId,
    contactName,
    onClose,
    canManage = true,
}: {
    contactId: number | null;
    contactName?: string;
    onClose: () => void;
    /** Sin permiso contacts.consents: solo se ven los consentimientos. */
    canManage?: boolean;
}) {
    const { data: consents, isLoading } = useConsents(contactId);
    const grantConsent = useGrantConsent(contactId);
    const revokeConsent = useRevokeConsent(contactId);

    const [grantingChannel, setGrantingChannel] = useState<TemplateChannel | null>(null);
    const [revoking, setRevoking] = useState<Consent | null>(null);

    const {
        register,
        handleSubmit,
        reset,
        formState: { errors },
    } = useForm<ConsentFormValues>({
        resolver: zodResolver(consentSchema),
        defaultValues: { source: '', evidence_text: '' },
    });

    const openGrantForm = (channel: TemplateChannel) => {
        setGrantingChannel(channel);
        reset({ source: '', evidence_text: '' });
        grantConsent.reset();
    };

    const onGrant = handleSubmit((values) => {
        if (!grantingChannel) return;

        grantConsent.mutate(
            { channel: grantingChannel, ...values },
            { onSuccess: () => setGrantingChannel(null) },
        );
    });

    const confirmRevoke = () => {
        if (!revoking) return;
        revokeConsent.mutate(revoking.id, { onSettled: () => setRevoking(null) });
    };

    // Un contacto puede tener varias filas históricas por canal (otorgado,
    // revocado, otorgado de nuevo) — solo nos interesa la más reciente.
    const latestByChannel = (channel: TemplateChannel): Consent | undefined =>
        consents
            ?.filter((c) => c.channel === channel)
            .toSorted((a, b) => new Date(b.granted_at).getTime() - new Date(a.granted_at).getTime())[0];

    return (
        <>
            <Dialog open={contactId !== null} onOpenChange={(isOpen) => !isOpen && onClose()}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {contactName ? `Consentimientos de ${contactName}` : 'Consentimientos'}
                        </DialogTitle>
                        <DialogDescription>
                            Evidencia de consentimiento por canal (Ley N° 32323): quién lo autorizó, de dónde vino y el
                            texto exacto que aceptó. Revocar tiene efecto inmediato.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-3">
                        {isLoading &&
                            CHANNELS.map((channel) => <Skeleton key={channel} className="h-14 w-full rounded-lg" />)}

                        {!isLoading &&
                            CHANNELS.map((channel) => {
                                const consent = latestByChannel(channel);
                                const isActive = consent?.is_active ?? false;

                                return (
                                    <div key={channel} className="rounded-lg border p-4">
                                        <div className="flex items-center justify-between gap-3">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <ChannelBadge channel={channel} />
                                                {isActive && consent && (
                                                    <Badge variant="success">
                                                        Vigente desde {formatDateTime(consent.granted_at)}
                                                    </Badge>
                                                )}
                                                {!isActive && consent && (
                                                    <Badge variant="neutral">
                                                        Revocado el{' '}
                                                        {formatDateTime(consent.revoked_at ?? consent.granted_at)}
                                                    </Badge>
                                                )}
                                                {!consent && <Badge variant="neutral">Sin registrar</Badge>}
                                            </div>

                                            {!canManage ? null : isActive && consent ? (
                                                <Button
                                                    variant="ghosterror"
                                                    size="sm"
                                                    className="text-error"
                                                    onClick={() => setRevoking(consent)}
                                                    disabled={revokeConsent.isPending}
                                                >
                                                    <ShieldOff />
                                                    Revocar
                                                </Button>
                                            ) : (
                                                <Button
                                                    variant="ghostsuccess"
                                                    size="sm"
                                                    className="text-success"
                                                    onClick={() => openGrantForm(channel)}
                                                >
                                                    <ShieldCheck />
                                                    Otorgar
                                                </Button>
                                            )}
                                        </div>

                                        {consent && (
                                            <p className="mt-2 text-xs text-muted-foreground">
                                                Origen: {consent.source} · &ldquo;{consent.evidence_text}&rdquo;
                                            </p>
                                        )}

                                        {grantingChannel === channel && (
                                            <form onSubmit={onGrant} className="mt-4 space-y-4 border-t pt-4">
                                                {grantConsent.isError && (
                                                    <Alert variant="error">
                                                        <XCircle />
                                                        <AlertTitle>
                                                            {apiErrorMessage(
                                                                grantConsent.error,
                                                                [],
                                                                'No se pudo registrar el consentimiento.',
                                                            )}
                                                        </AlertTitle>
                                                    </Alert>
                                                )}

                                                <Field
                                                    label="Origen del consentimiento"
                                                    htmlFor={`consent-source-${channel}`}
                                                    error={errors.source?.message}
                                                >
                                                    <Input
                                                        id={`consent-source-${channel}`}
                                                        placeholder="ej. formulario web, opt-in por WhatsApp, registro telefónico"
                                                        aria-invalid={Boolean(errors.source)}
                                                        {...register('source')}
                                                    />
                                                </Field>
                                                <Field
                                                    label="Texto exacto que aceptó el contacto"
                                                    htmlFor={`consent-evidence-${channel}`}
                                                    error={errors.evidence_text?.message}
                                                >
                                                    <Textarea
                                                        id={`consent-evidence-${channel}`}
                                                        rows={2}
                                                        placeholder="ej. Acepto recibir comunicaciones comerciales de AlertPrompt por este canal."
                                                        aria-invalid={Boolean(errors.evidence_text)}
                                                        {...register('evidence_text')}
                                                    />
                                                </Field>

                                                <DialogFooter>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => setGrantingChannel(null)}
                                                    >
                                                        Cancelar
                                                    </Button>
                                                    <Button
                                                        type="submit"
                                                        variant="success"
                                                        size="sm"
                                                        loading={grantConsent.isPending}
                                                    >
                                                        Confirmar consentimiento
                                                    </Button>
                                                </DialogFooter>
                                            </form>
                                        )}
                                    </div>
                                );
                            })}
                    </div>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={revoking !== null}
                title="Revocar consentimiento"
                description={`¿Revocar el consentimiento de ${contactName ?? 'este contacto'} para ${revoking?.channel ?? ''}? Tiene efecto inmediato y no se puede deshacer.`}
                confirmLabel="Revocar"
                destructive
                loading={revokeConsent.isPending}
                onConfirm={confirmRevoke}
                onCancel={() => setRevoking(null)}
            />
        </>
    );
}
