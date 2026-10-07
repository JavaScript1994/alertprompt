import { zodResolver } from '@hookform/resolvers/zod';
import { XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { z } from 'zod';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { useCampaign, useUpdateCampaign } from '@/hooks/useCampaigns';
import { useApprovedTemplates } from '@/hooks/useTemplates';
import { apiErrorMessage } from '@/lib/format';
import ContactPicker from './ContactPicker';
import TemplateSelect from './TemplateSelect';

const campaignSchema = z.object({
    name: z.string().min(1, 'Ingresa un nombre').max(255),
    template_id: z.number().min(1, 'Elegí una plantilla'),
});

type CampaignFormValues = z.infer<typeof campaignSchema>;

// Input datetime-local necesita "YYYY-MM-DDTHH:mm" en hora local, sin
// segundos ni zona horaria.
function toDatetimeLocal(iso: string): string {
    const date = new Date(iso);
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export default function CampaignFormModal({ campaignId, onClose }: { campaignId: number | null; onClose: () => void }) {
    const { data: campaign, isLoading } = useCampaign(campaignId);
    const { data: templates } = useApprovedTemplates();
    const updateCampaign = useUpdateCampaign();

    const [selectedContactIds, setSelectedContactIds] = useState<number[]>([]);
    const [scheduledAt, setScheduledAt] = useState('');
    const [audienceError, setAudienceError] = useState<string | null>(null);
    const [generalError, setGeneralError] = useState<string | null>(null);

    const {
        register,
        control,
        handleSubmit,
        reset,
        formState: { errors },
    } = useForm<CampaignFormValues>({
        resolver: zodResolver(campaignSchema),
        defaultValues: { name: '', template_id: 0 },
    });

    useEffect(() => {
        if (!campaign) return;

        reset({ name: campaign.name, template_id: campaign.template.id });
        setSelectedContactIds(campaign.recipients?.map((r) => r.contact_id) ?? []);
        setScheduledAt(campaign.scheduled_at ? toDatetimeLocal(campaign.scheduled_at) : '');
        setAudienceError(null);
        setGeneralError(null);
        updateCampaign.reset();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [campaign?.id]);

    const initialContactDetails = campaign?.recipients?.reduce<
        Record<number, { name: string; phone: string | null; email: string | null }>
    >((acc, r) => {
        acc[r.contact_id] = { name: r.name, phone: r.phone, email: r.email };
        return acc;
    }, {});

    const onSubmit = handleSubmit((values) => {
        if (!campaign) return;

        if (selectedContactIds.length === 0) {
            setAudienceError('Elegí al menos un contacto.');
            return;
        }
        setAudienceError(null);
        setGeneralError(null);

        updateCampaign.mutate(
            {
                id: campaign.id,
                ...values,
                contact_ids: selectedContactIds,
                scheduled_at: scheduledAt ? new Date(scheduledAt).toISOString() : undefined,
            },
            {
                onSuccess: onClose,
                onError: (error) =>
                    setGeneralError(
                        apiErrorMessage(
                            error,
                            ['template_id', 'contact_ids', 'scheduled_at'],
                            'No se pudo guardar la campaña.',
                        ),
                    ),
            },
        );
    });

    return (
        <Dialog open={campaignId !== null} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Editar campaña</DialogTitle>
                    <DialogDescription>Solo se pueden editar campañas en borrador o programadas.</DialogDescription>
                </DialogHeader>

                {isLoading || !campaign ? (
                    <div className="space-y-4">
                        <Skeleton className="h-10 w-full" />
                        <Skeleton className="h-10 w-full" />
                        <Skeleton className="h-40 w-full" />
                    </div>
                ) : (
                    <form onSubmit={onSubmit} className="space-y-4">
                        <Field label="Nombre" htmlFor="edit-campaign-name" error={errors.name?.message}>
                            <Input
                                id="edit-campaign-name"
                                placeholder="Promo de fin de semana"
                                aria-invalid={Boolean(errors.name)}
                                {...register('name')}
                            />
                        </Field>

                        <Field label="Plantilla" htmlFor="edit-campaign-template" error={errors.template_id?.message}>
                            <Controller
                                control={control}
                                name="template_id"
                                render={({ field }) => (
                                    <TemplateSelect
                                        id="edit-campaign-template"
                                        templates={templates}
                                        value={field.value}
                                        onChange={field.onChange}
                                        invalid={Boolean(errors.template_id)}
                                    />
                                )}
                            />
                        </Field>

                        <ContactPicker
                            selected={selectedContactIds}
                            onChange={setSelectedContactIds}
                            initialDetails={initialContactDetails}
                            error={audienceError}
                        />

                        <Field
                            label="Programar para (opcional)"
                            htmlFor="edit-campaign-scheduled-at"
                            hint="Si lo dejás vacío, la campaña queda como borrador."
                        >
                            <Input
                                id="edit-campaign-scheduled-at"
                                type="datetime-local"
                                value={scheduledAt}
                                onChange={(event) => setScheduledAt(event.target.value)}
                            />
                        </Field>

                        {generalError && (
                            <Alert variant="error">
                                <XCircle />
                                <AlertTitle>{generalError}</AlertTitle>
                            </Alert>
                        )}

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={onClose}>
                                Cancelar
                            </Button>
                            <Button type="submit" loading={updateCampaign.isPending}>
                                {updateCampaign.isPending ? 'Guardando…' : 'Guardar cambios'}
                            </Button>
                        </DialogFooter>
                    </form>
                )}
            </DialogContent>
        </Dialog>
    );
}
