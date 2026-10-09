import { zodResolver } from '@hookform/resolvers/zod';
import { Megaphone, XCircle } from 'lucide-react';
import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { z } from 'zod';
import ConfirmDialog from '@/components/shared/ConfirmDialog';
import EmptyState from '@/components/shared/EmptyState';
import PageHeader from '@/components/shared/PageHeader';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { useAuthUser } from '@/hooks/useAuth';
import { useCampaigns, useCreateCampaign, useDeleteCampaign, useDispatchCampaign } from '@/hooks/useCampaigns';
import { useCan } from '@/hooks/usePermissions';
import { useApprovedTemplates } from '@/hooks/useTemplates';
import { apiErrorMessage } from '@/lib/format';
import type { Campaign } from '@/types';
import CampaignFormModal from './CampaignFormModal';
import CampaignsTable from './CampaignsTable';
import ContactPicker from './ContactPicker';
import TemplateSelect from './TemplateSelect';

const campaignSchema = z.object({
    name: z.string().min(1, 'Ingresa un nombre').max(255),
    template_id: z.number().min(1, 'Elegí una plantilla'),
});

type CampaignFormValues = z.infer<typeof campaignSchema>;

export default function Campaigns() {
    const can = useCan();
    const canCreate = can('campaigns.create');
    // En modo soporte el backend rechaza disparos: ni se ofrece el botón.
    const { data: authUser } = useAuthUser();
    const canDispatch = can('campaigns.dispatch') && !authUser?.impersonating;
    const [page, setPage] = useState(1);
    const { data, isLoading } = useCampaigns(page);
    const { data: templates } = useApprovedTemplates();
    const createCampaign = useCreateCampaign();
    const dispatchCampaign = useDispatchCampaign();
    const deleteCampaign = useDeleteCampaign();
    const [editingCampaignId, setEditingCampaignId] = useState<number | null>(null);
    const [deleting, setDeleting] = useState<Campaign | null>(null);

    const confirmDelete = () => {
        if (!deleting) return;
        deleteCampaign.mutate(deleting.id, { onSettled: () => setDeleting(null) });
    };

    const [selectedContactIds, setSelectedContactIds] = useState<number[]>([]);
    const [audienceError, setAudienceError] = useState<string | null>(null);
    const [scheduledAt, setScheduledAt] = useState('');

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

    return (
        <div>
            <PageHeader title="Campañas" description="Lanzá y seguí el progreso de tus campañas en vivo." />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-12">
                {canCreate && (
                <section className="xl:col-span-5">
                    <Card>
                        <CardHeader>
                            <CardTitle>Nueva campaña</CardTitle>
                            <CardDescription>Elegí plantilla, destinatarios y, si querés, fecha de envío.</CardDescription>
                        </CardHeader>

                        <form onSubmit={onSubmit} className="space-y-4">
                            <Field label="Nombre" htmlFor="campaign-name" error={errors.name?.message}>
                                <Input
                                    id="campaign-name"
                                    placeholder="Promo de fin de semana"
                                    aria-invalid={Boolean(errors.name)}
                                    {...register('name')}
                                />
                            </Field>

                            <Field
                                label="Plantilla"
                                htmlFor="campaign-template"
                                error={errors.template_id?.message}
                                hint={
                                    templates?.length === 0
                                        ? 'No hay plantillas aprobadas todavía. Aprobá una desde la sección de plantillas.'
                                        : undefined
                                }
                            >
                                <Controller
                                    control={control}
                                    name="template_id"
                                    render={({ field }) => (
                                        <TemplateSelect
                                            id="campaign-template"
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
                                error={audienceError}
                            />

                            <Field
                                label="Programar para (opcional)"
                                htmlFor="campaign-scheduled-at"
                                hint="Si lo dejás vacío, la campaña queda como borrador y la iniciás vos manualmente."
                            >
                                <Input
                                    id="campaign-scheduled-at"
                                    type="datetime-local"
                                    value={scheduledAt}
                                    onChange={(event) => setScheduledAt(event.target.value)}
                                />
                            </Field>

                            {createCampaign.isError && (
                                <Alert variant="error">
                                    <XCircle />
                                    <AlertTitle>
                                        {apiErrorMessage(
                                            createCampaign.error,
                                            ['template_id', 'contact_ids', 'scheduled_at'],
                                            'Error al crear la campaña.',
                                        )}
                                    </AlertTitle>
                                </Alert>
                            )}

                            <Button type="submit" loading={createCampaign.isPending} className="w-full">
                                {createCampaign.isPending ? 'Creando…' : 'Crear campaña'}
                            </Button>
                        </form>
                    </Card>
                </section>
                )}

                <section className={canCreate ? 'xl:col-span-7' : 'xl:col-span-12'}>
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="text-base">Todas las campañas</h2>
                        <span className="flex items-center gap-1.5 text-xs whitespace-nowrap text-muted-foreground">
                            <span className="size-1.5 animate-pulse rounded-full bg-whatsapp-500" />
                            en vivo
                        </span>
                    </div>

                    {deleteCampaign.isError && (
                        <Alert variant="error" className="mb-3">
                            <XCircle />
                            <AlertTitle>
                                {apiErrorMessage(deleteCampaign.error, [], 'No se pudo eliminar la campaña.')}
                            </AlertTitle>
                        </Alert>
                    )}

                    {isLoading && (
                        <Card className="gap-4">
                            {Array.from({ length: 5 }).map((_, i) => (
                                <Skeleton key={i} className="h-12 w-full" />
                            ))}
                        </Card>
                    )}

                    {!isLoading && data?.data.length === 0 && (
                        <EmptyState
                            icon={Megaphone}
                            title="No hay campañas todavía"
                            description="Creá tu primera campaña desde el formulario."
                        />
                    )}

                    {!isLoading && data && data.data.length > 0 && (
                        <CampaignsTable
                            data={data}
                            onDispatch={canDispatch ? (campaign) => dispatchCampaign.mutate(campaign.id) : undefined}
                            dispatchPendingId={dispatchCampaign.isPending ? dispatchCampaign.variables : null}
                            onEdit={can('campaigns.update') ? (campaign) => setEditingCampaignId(campaign.id) : undefined}
                            onDelete={can('campaigns.delete') ? setDeleting : undefined}
                            deletePendingId={deleteCampaign.isPending ? deleteCampaign.variables : null}
                            onPageChange={setPage}
                        />
                    )}
                </section>
            </div>

            <CampaignFormModal campaignId={editingCampaignId} onClose={() => setEditingCampaignId(null)} />

            <ConfirmDialog
                open={deleting !== null}
                title="Eliminar campaña"
                description={`¿Eliminar la campaña "${deleting?.name ?? ''}"? Esta acción no se puede deshacer.`}
                confirmLabel="Eliminar"
                destructive
                loading={deleteCampaign.isPending}
                onConfirm={confirmDelete}
                onCancel={() => setDeleting(null)}
            />
        </div>
    );
}
