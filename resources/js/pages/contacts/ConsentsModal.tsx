import { zodResolver } from '@hookform/resolvers/zod';
import { AxiosError } from 'axios';
import { ShieldCheck, ShieldOff } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import Alert from '@/components/ui/Alert';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import ChannelBadge from '@/components/ui/ChannelBadge';
import Input from '@/components/ui/Input';
import Modal from '@/components/ui/Modal';
import Textarea from '@/components/ui/Textarea';
import { useConsents, useGrantConsent, useRevokeConsent } from '@/hooks/useConsents';
import type { Consent, TemplateChannel } from '@/types';

const CHANNELS: TemplateChannel[] = ['whatsapp', 'sms', 'email'];

const consentSchema = z.object({
    source: z.string().min(1, 'Indicá el origen').max(255),
    evidence_text: z.string().min(1, 'Ingresá el texto exacto que aceptó el contacto').max(2000),
});

type ConsentFormValues = z.infer<typeof consentSchema>;

function formatDate(iso: string): string {
    return new Date(iso).toLocaleString('es-PE', { dateStyle: 'medium', timeStyle: 'short' });
}

export default function ConsentsModal({
    contactId,
    contactName,
    onClose,
}: {
    contactId: number | null;
    contactName?: string;
    onClose: () => void;
}) {
    const { data: consents, isLoading } = useConsents(contactId);
    const grantConsent = useGrantConsent(contactId);
    const revokeConsent = useRevokeConsent(contactId);

    const [grantingChannel, setGrantingChannel] = useState<TemplateChannel | null>(null);

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

    const onRevoke = (consent: Consent) => {
        if (
            window.confirm(
                `¿Revocar el consentimiento de ${contactName ?? 'este contacto'} para ${consent.channel}? Tiene efecto inmediato y no se puede deshacer.`,
            )
        ) {
            revokeConsent.mutate(consent.id);
        }
    };

    // Un contacto puede tener varias filas históricas por canal (otorgado,
    // revocado, otorgado de nuevo) — solo nos interesa la más reciente.
    const latestByChannel = (channel: TemplateChannel): Consent | undefined =>
        consents
            ?.filter((c) => c.channel === channel)
            .sort((a, b) => new Date(b.granted_at).getTime() - new Date(a.granted_at).getTime())[0];

    return (
        <Modal
            open={contactId !== null}
            onClose={onClose}
            title={contactName ? `Consentimientos de ${contactName}` : 'Consentimientos'}
            size="md"
        >
            <div className="space-y-3 p-6">
                <p className="text-xs text-slate-500">
                    Evidencia de consentimiento por canal (Ley N° 32323): quién lo autorizó, de dónde vino y el texto
                    exacto que aceptó. Revocar tiene efecto inmediato.
                </p>

                {isLoading && <p className="text-sm text-slate-400">Cargando…</p>}

                {!isLoading &&
                    CHANNELS.map((channel) => {
                        const consent = latestByChannel(channel);
                        const isActive = consent?.is_active ?? false;

                        return (
                            <div key={channel} className="rounded-lg border border-slate-200 p-3.5">
                                <div className="flex items-center justify-between gap-3">
                                    <div className="flex items-center gap-2">
                                        <ChannelBadge channel={channel} />
                                        {isActive && consent && (
                                            <Badge variant="success">Vigente desde {formatDate(consent.granted_at)}</Badge>
                                        )}
                                        {!isActive && consent && (
                                            <Badge variant="neutral">
                                                Revocado el {formatDate(consent.revoked_at ?? consent.granted_at)}
                                            </Badge>
                                        )}
                                        {!consent && <Badge variant="neutral">Sin registrar</Badge>}
                                    </div>

                                    {isActive && consent ? (
                                        <button
                                            onClick={() => onRevoke(consent)}
                                            disabled={revokeConsent.isPending}
                                            className="inline-flex items-center gap-1 text-xs font-medium text-red-600 hover:text-red-700 disabled:opacity-50"
                                        >
                                            <ShieldOff className="h-3.5 w-3.5" />
                                            Revocar
                                        </button>
                                    ) : (
                                        <button
                                            onClick={() => openGrantForm(channel)}
                                            className="inline-flex items-center gap-1 text-xs font-medium text-whatsapp-700 hover:text-whatsapp-800"
                                        >
                                            <ShieldCheck className="h-3.5 w-3.5" />
                                            Otorgar
                                        </button>
                                    )}
                                </div>

                                {consent && (
                                    <p className="mt-2 text-xs text-slate-400">
                                        Origen: {consent.source} · &ldquo;{consent.evidence_text}&rdquo;
                                    </p>
                                )}

                                {grantingChannel === channel && (
                                    <form onSubmit={onGrant} className="mt-3 space-y-3 border-t border-slate-100 pt-3">
                                        {grantConsent.isError && (
                                            <Alert type="error">
                                                {grantConsent.error instanceof AxiosError
                                                    ? (grantConsent.error.response?.data?.message ??
                                                      'No se pudo registrar el consentimiento.')
                                                    : 'No se pudo registrar el consentimiento.'}
                                            </Alert>
                                        )}

                                        <Input
                                            label="Origen del consentimiento"
                                            placeholder="ej. formulario web, opt-in por WhatsApp, registro telefónico"
                                            error={errors.source?.message}
                                            {...register('source')}
                                        />
                                        <Textarea
                                            label="Texto exacto que aceptó el contacto"
                                            rows={2}
                                            placeholder="ej. Acepto recibir comunicaciones comerciales de AlertPrompt por este canal."
                                            error={errors.evidence_text?.message}
                                            {...register('evidence_text')}
                                        />

                                        <div className="flex justify-end gap-2">
                                            <Button
                                                type="button"
                                                variant="secondary"
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
                                        </div>
                                    </form>
                                )}
                            </div>
                        );
                    })}
            </div>
        </Modal>
    );
}
