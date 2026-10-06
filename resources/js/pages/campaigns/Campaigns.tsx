import { zodResolver } from '@hookform/resolvers/zod';
import { AxiosError } from 'axios';
import { Megaphone } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import EmptyState from '@/components/ui/EmptyState';
import Input from '@/components/ui/Input';
import PageHeader from '@/components/ui/PageHeader';
import Select from '@/components/ui/Select';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { useCampaigns, useCreateCampaign, useDeleteCampaign, useDispatchCampaign } from '@/hooks/useCampaigns';
import { useApprovedTemplates } from '@/hooks/useTemplates';
import type { Campaign } from '@/types';
import CampaignFormModal from './CampaignFormModal';
import CampaignsTable from './CampaignsTable';
import ContactPicker from './ContactPicker';

const campaignSchema = z.object({
    name: z.string().min(1, 'Ingresa un nombre').max(255),
    template_id: z.number().min(1, 'Elegí una plantilla'),
});

type CampaignFormValues = z.infer<typeof campaignSchema>;

export default function Campaigns() {
    const [page, setPage] = useState(1);
    const { data, isLoading } = useCampaigns(page);
    const { data: templates } = useApprovedTemplates();
    const createCampaign = useCreateCampaign();
    const dispatchCampaign = useDispatchCampaign();
    const deleteCampaign = useDeleteCampaign();
    const [editingCampaignId, setEditingCampaignId] = useState<number | null>(null);

    const onDelete = (campaign: Campaign) => {
        if (window.confirm(`¿Eliminar la campaña "${campaign.name}"? Esta acción no se puede deshacer.`)) {
            deleteCampaign.mutate(campaign.id);
        }
    };

    const [selectedContactIds, setSelectedContactIds] = useState<number[]>([]);
    const [audienceError, setAudienceError] = useState<string | null>(null);
    const [scheduledAt, setScheduledAt] = useState('');

    const {
        register,
        handleSubmit,
        reset,
        formState: { errors },
    } = useForm<CampaignFormValues>({
        resolver: zodResolver(campaignSchema),
        defaultValues: { name: '', template_id: 0 },
    });

    const onSubmit = handleSubmit((values) => {
        if (selectedContactIds.length === 0) {
            setAudienceError('Elegí al menos un contacto.');
            return;
        }
        setAudienceError(null);

        createCampaign.mutate(
            {
                ...values,
                contact_ids: selectedContactIds,
                scheduled_at: scheduledAt ? new Date(scheduledAt).toISOString() : undefined,
            },
            {
                onSuccess: () => {
                    reset({ name: '', template_id: 0 });
                    setSelectedContactIds([]);
                    setScheduledAt('');
                },
            },
        );
    });

    const createError =
        createCampaign.error instanceof AxiosError
            ? (createCampaign.error.response?.data?.errors?.template_id?.[0] ??
              createCampaign.error.response?.data?.errors?.contact_ids?.[0] ??
              createCampaign.error.response?.data?.errors?.scheduled_at?.[0] ??
              'Error al crear la campaña.')
            : audienceError;

    return (
        <div>
            <PageHeader title="Campañas" description="Lanzá y seguí el progreso de tus campañas en vivo." />

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <section>
                    <h2 className="mb-3 text-sm font-semibold text-ink-900">Nueva campaña</h2>

                    <form
                        onSubmit={onSubmit}
                        className="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <Input
                            label="Nombre"
                            placeholder="Promo de fin de semana"
                            error={errors.name?.message}
                            {...register('name')}
                        />

                        <Select
                            label="Plantilla"
                            error={errors.template_id?.message}
                            hint={
                                templates?.length === 0
                                    ? 'No hay plantillas aprobadas todavía. Aprobá una desde la sección de plantillas.'
                                    : undefined
                            }
                            {...register('template_id', { valueAsNumber: true })}
                        >
                            <option value={0}>Elegí una plantilla aprobada…</option>
                            {templates?.map((template) => (
                                <option key={template.id} value={template.id}>
                                    {template.name} ({template.channel})
                                </option>
                            ))}
                        </Select>

                        <ContactPicker selected={selectedContactIds} onChange={setSelectedContactIds} />

                        <Input
                            type="datetime-local"
                            label="Programar para (opcional)"
                            hint="Si lo dejás vacío, la campaña queda como borrador y la iniciás vos manualmente."
                            value={scheduledAt}
                            onChange={(event) => setScheduledAt(event.target.value)}
                        />

                        {createError && <Alert type="error">{createError}</Alert>}

                        <Button type="submit" loading={createCampaign.isPending} className="w-full">
                            {createCampaign.isPending ? 'Creando…' : 'Crear campaña'}
                        </Button>
                    </form>
                </section>

                <section>
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="text-sm font-semibold text-ink-900">Todas las campañas</h2>
                        <span className="flex items-center gap-1.5 text-xs text-slate-400">
                            <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-whatsapp-500" />
                            en vivo
                        </span>
                    </div>

                    {deleteCampaign.isError && (
                        <Alert type="error" className="mb-3">
                            {deleteCampaign.error instanceof AxiosError
                                ? (deleteCampaign.error.response?.data?.message ??
                                  'No se pudo eliminar la campaña.')
                                : 'No se pudo eliminar la campaña.'}
                        </Alert>
                    )}

                    {isLoading && (
                        <div className="rounded-xl border border-slate-200 bg-white p-5">
                            <SkeletonTable rows={5} />
                        </div>
                    )}

                    {!isLoading && data?.data.length === 0 && (
                        <EmptyState
                            icon={Megaphone}
                            title="No hay campañas todavía"
                            description="Creá tu primera campaña desde el formulario de la izquierda."
                        />
                    )}

                    {!isLoading && data && data.data.length > 0 && (
                        <CampaignsTable
                            data={data}
                            onDispatch={(campaign) => dispatchCampaign.mutate(campaign.id)}
                            dispatchPendingId={dispatchCampaign.isPending ? dispatchCampaign.variables : null}
                            onEdit={(campaign) => setEditingCampaignId(campaign.id)}
                            onDelete={onDelete}
                            deletePendingId={deleteCampaign.isPending ? deleteCampaign.variables : null}
                            onPageChange={setPage}
                        />
                    )}
                </section>
            </div>

            <CampaignFormModal campaignId={editingCampaignId} onClose={() => setEditingCampaignId(null)} />
        </div>
    );
}
