import { zodResolver } from '@hookform/resolvers/zod';
import { AxiosError } from 'axios';
import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';
import Modal from '@/components/ui/Modal';
import Select from '@/components/ui/Select';
import { SkeletonCard } from '@/components/ui/Skeleton';
import { useCampaign, useUpdateCampaign } from '@/hooks/useCampaigns';
import { useApprovedTemplates } from '@/hooks/useTemplates';
import ContactPicker from './ContactPicker';

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
                onError: (error) => {
                    if (error instanceof AxiosError) {
                        setGeneralError(
                            error.response?.data?.errors?.template_id?.[0] ??
                                error.response?.data?.errors?.contact_ids?.[0] ??
                                error.response?.data?.errors?.scheduled_at?.[0] ??
                                error.response?.data?.message ??
                                'No se pudo guardar la campaña.',
                        );
                        return;
                    }
                    setGeneralError('No se pudo guardar la campaña.');
                },
            },
        );
    });

    return (
        <Modal open={campaignId !== null} onClose={onClose} title="Editar campaña" size="lg">
            {isLoading || !campaign ? (
                <div className="p-6">
                    <SkeletonCard />
                </div>
            ) : (
                <form onSubmit={onSubmit} className="space-y-4 p-6">
                    <Input
                        label="Nombre"
                        placeholder="Promo de fin de semana"
                        error={errors.name?.message}
                        {...register('name')}
                    />

                    <Select
                        label="Plantilla"
                        error={errors.template_id?.message}
                        {...register('template_id', { valueAsNumber: true })}
                    >
                        <option value={0}>Elegí una plantilla aprobada…</option>
                        {templates?.map((template) => (
                            <option key={template.id} value={template.id}>
                                {template.name} ({template.channel})
                            </option>
                        ))}
                    </Select>

                    <ContactPicker
                        selected={selectedContactIds}
                        onChange={setSelectedContactIds}
                        initialDetails={initialContactDetails}
                    />
                    {audienceError && <p className="text-sm text-red-600">{audienceError}</p>}

                    <Input
                        type="datetime-local"
                        label="Programar para (opcional)"
                        hint="Si lo dejás vacío, la campaña queda como borrador."
                        value={scheduledAt}
                        onChange={(event) => setScheduledAt(event.target.value)}
                    />

                    {generalError && <Alert type="error">{generalError}</Alert>}

                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancelar
                        </Button>
                        <Button type="submit" loading={updateCampaign.isPending}>
                            {updateCampaign.isPending ? 'Guardando…' : 'Guardar cambios'}
                        </Button>
                    </div>
                </form>
            )}
        </Modal>
    );
}
