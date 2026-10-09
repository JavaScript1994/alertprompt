import { zodResolver } from '@hookform/resolvers/zod';
import { CheckCircle2, MessageSquareText, TriangleAlert, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { z } from 'zod';
import ConfirmDialog from '@/components/shared/ConfirmDialog';
import EmptyState from '@/components/shared/EmptyState';
import PageHeader from '@/components/shared/PageHeader';
import { TemplateStatusBadge } from '@/components/shared/StatusBadge';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import {
    useCreateTemplate,
    useDeleteTemplate,
    usePreviewTemplate,
    useTemplates,
    useUpdateTemplate,
} from '@/hooks/useTemplates';
import { useEnabledChannels } from '@/hooks/useModules';
import { useCan } from '@/hooks/usePermissions';
import { apiErrorMessage } from '@/lib/format';
import type { Template } from '@/types';
import TemplatesTable from './TemplatesTable';

const CHANNEL_LABELS = { whatsapp: 'WhatsApp', sms: 'SMS', email: 'Email' } as const;

const templateSchema = z.object({
    channel: z.enum(['whatsapp', 'sms', 'email']),
    category: z.enum(['marketing', 'utility', 'authentication']),
    name: z.string().min(1, 'Ingresa un nombre').max(255),
    body: z.string().min(1, 'Ingresa el contenido del mensaje').max(4096),
});

type TemplateFormValues = z.infer<typeof templateSchema>;

export default function Templates() {
    const can = useCan();
    const enabledChannels = useEnabledChannels();
    // Sin ningún canal contratado no hay con qué crear plantillas.
    const canCreate = can('templates.create') && enabledChannels.length > 0;
    const [page, setPage] = useState(1);
    const { data, isLoading } = useTemplates(page);
    const createTemplate = useCreateTemplate();
    const updateTemplate = useUpdateTemplate();
    const deleteTemplate = useDeleteTemplate();
    const preview = usePreviewTemplate();

    // Plantilla recién creada: se muestra debajo del formulario con su
    // estado y la acción para aprobarla, ya que no hay ningún flujo
    // automático de revisión en este MVP — todas nacen en "Borrador".
    const [justCreated, setJustCreated] = useState<Template | null>(null);
    const [deleting, setDeleting] = useState<Template | null>(null);

    const confirmDelete = () => {
        if (!deleting) return;
        deleteTemplate.mutate(deleting.id, { onSettled: () => setDeleting(null) });
    };

    const onApproveTemplate = (template: Template) => {
        updateTemplate.mutate(
            {
                id: template.id,
                channel: template.channel,
                category: template.category,
                name: template.name,
                body: template.body,
                status: 'approved',
            },
            {
                onSuccess: (updated) => {
                    if (justCreated?.id === template.id) {
                        setJustCreated(updated);
                    }
                },
            },
        );
    };

    const [sampleData, setSampleData] = useState<Record<string, string>>({});

    const {
        register,
        control,
        handleSubmit,
        watch,
        reset,
        formState: { errors },
    } = useForm<TemplateFormValues>({
        resolver: zodResolver(templateSchema),
        defaultValues: { channel: enabledChannels[0] ?? 'sms', category: 'marketing', name: '', body: '' },
    });

    const body = watch('body');

    // Preview en vivo: debounced para no pegarle al backend en cada tecla.
    // El parseo de variables vive solo en TemplateRenderer (backend) — no lo
    // duplicamos en el cliente.
    useEffect(() => {
        if (!body) return;
        const timeout = setTimeout(() => {
            preview.mutate({ body, sampleData });
        }, 400);
        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [body, sampleData]);

    const onSubmit = handleSubmit((values) => {
        createTemplate.mutate(values, {
            onSuccess: (template) => {
                reset({ channel: values.channel, category: values.category, name: '', body: '' });
                setSampleData({});
                setJustCreated(template);
            },
        });
    });

    return (
        <div>
            <PageHeader title="Plantillas" description="Definí el contenido de tus mensajes con variables dinámicas." />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-12">
                {canCreate && (
                <section className="space-y-6 xl:col-span-5">
                    <Card>
                        <CardHeader>
                            <CardTitle>Nueva plantilla</CardTitle>
                            <CardDescription>Todas nacen en Borrador y se aprueban antes de usarse.</CardDescription>
                        </CardHeader>

                        <form onSubmit={onSubmit} className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <Field label="Canal" htmlFor="template-channel">
                                    <Controller
                                        control={control}
                                        name="channel"
                                        render={({ field }) => (
                                            <Select value={field.value} onValueChange={field.onChange}>
                                                <SelectTrigger id="template-channel">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {enabledChannels.map((channel) => (
                                                        <SelectItem key={channel} value={channel}>
                                                            {CHANNEL_LABELS[channel]}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        )}
                                    />
                                </Field>
                                <Field label="Categoría" htmlFor="template-category">
                                    <Controller
                                        control={control}
                                        name="category"
                                        render={({ field }) => (
                                            <Select value={field.value} onValueChange={field.onChange}>
                                                <SelectTrigger id="template-category">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="marketing">Marketing</SelectItem>
                                                    <SelectItem value="utility">Utility</SelectItem>
                                                    <SelectItem value="authentication">Authentication</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        )}
                                    />
                                </Field>
                            </div>

                            <Field label="Nombre" htmlFor="template-name" error={errors.name?.message}>
                                <Input
                                    id="template-name"
                                    placeholder="bienvenida_nuevo_cliente"
                                    aria-invalid={Boolean(errors.name)}
                                    {...register('name')}
                                />
                            </Field>

                            <Field
                                label={
                                    <>
                                        Contenido
                                        <span className="font-normal text-muted-foreground">
                                            — usa {'{{variable}}'} para campos dinámicos
                                        </span>
                                    </>
                                }
                                htmlFor="template-body"
                                error={errors.body?.message}
                            >
                                <Textarea
                                    id="template-body"
                                    rows={5}
                                    className="font-mono"
                                    placeholder="Hola {{nombre}}, tu pedido {{pedido}} está listo."
                                    aria-invalid={Boolean(errors.body)}
                                    {...register('body')}
                                />
                            </Field>

                            {createTemplate.isError && (
                                <Alert variant="error">
                                    <XCircle />
                                    <AlertTitle>
                                        {apiErrorMessage(
                                            createTemplate.error,
                                            ['name', 'channel', 'category', 'body'],
                                            'No se pudo crear la plantilla.',
                                        )}
                                    </AlertTitle>
                                </Alert>
                            )}

                            <Button type="submit" loading={createTemplate.isPending} className="w-full">
                                {createTemplate.isPending ? 'Guardando…' : 'Crear plantilla'}
                            </Button>
                        </form>
                    </Card>

                    {justCreated && (
                        <Card className="gap-3">
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <p className="font-medium text-foreground">{justCreated.name}</p>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        Estado actual de la plantilla que acabás de crear.
                                    </p>
                                </div>
                                <TemplateStatusBadge status={justCreated.status} />
                            </div>

                            {justCreated.status === 'approved' ? (
                                <p className="flex items-center gap-1.5 text-xs text-success">
                                    <CheckCircle2 className="size-3.5" />
                                    Ya está aprobada — podés usarla para lanzar una campaña.
                                </p>
                            ) : (
                                <>
                                    <p className="text-xs text-muted-foreground">
                                        Las plantillas nuevas quedan en <strong>Borrador</strong>. Este MVP no tiene
                                        un flujo automático de revisión (en producción, WhatsApp exige que Meta
                                        apruebe cada plantilla) — para poder usarla en una campaña, alguien tiene
                                        que aprobarla manualmente.
                                    </p>
                                    <Button
                                        size="sm"
                                        variant="success"
                                        className="w-fit"
                                        onClick={() => onApproveTemplate(justCreated)}
                                        loading={updateTemplate.isPending}
                                    >
                                        Aprobar ahora
                                    </Button>
                                </>
                            )}
                        </Card>
                    )}

                    {preview.data && preview.data.variables.length > 0 && (
                        <Card className="gap-4">
                            <CardHeader>
                                <CardTitle className="text-base">Datos de prueba</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                {preview.data.variables.map((variable) => (
                                    <div key={variable} className="flex items-center gap-3">
                                        <label
                                            htmlFor={`sample-${variable}`}
                                            className="w-28 shrink-0 truncate font-mono text-sm text-muted-foreground"
                                        >
                                            {variable}
                                        </label>
                                        <Input
                                            id={`sample-${variable}`}
                                            className="h-9"
                                            value={sampleData[variable] ?? ''}
                                            onChange={(event) =>
                                                setSampleData((prev) => ({ ...prev, [variable]: event.target.value }))
                                            }
                                        />
                                    </div>
                                ))}
                            </CardContent>

                            <div>
                                <p className="mb-2 text-sm font-medium text-foreground">Vista previa</p>
                                <p className="rounded-lg bg-muted p-3 text-sm whitespace-pre-wrap text-foreground">
                                    {preview.data.rendered}
                                </p>
                                {preview.data.missing.length > 0 && (
                                    <p className="mt-2 flex items-center gap-1.5 text-xs text-warning">
                                        <TriangleAlert className="size-3.5" />
                                        Faltan valores para: {preview.data.missing.join(', ')}
                                    </p>
                                )}
                            </div>
                        </Card>
                    )}
                </section>
                )}

                <section className={canCreate ? 'xl:col-span-7' : 'xl:col-span-12'}>
                    <h2 className="mb-3 text-base">Todas las plantillas</h2>

                    {deleteTemplate.isError && (
                        <Alert variant="error" className="mb-3">
                            <XCircle />
                            <AlertTitle>
                                {apiErrorMessage(deleteTemplate.error, [], 'No se pudo eliminar la plantilla.')}
                            </AlertTitle>
                        </Alert>
                    )}
                    {updateTemplate.isError && (
                        <Alert variant="error" className="mb-3">
                            <XCircle />
                            <AlertTitle>
                                {apiErrorMessage(updateTemplate.error, [], 'No se pudo aprobar la plantilla.')}
                            </AlertTitle>
                        </Alert>
                    )}

                    {isLoading && (
                        <Card className="gap-4">
                            {Array.from({ length: 5 }).map((_, i) => (
                                <Skeleton key={i} className="h-10 w-full" />
                            ))}
                        </Card>
                    )}

                    {!isLoading && data?.data.length === 0 && (
                        <EmptyState
                            icon={MessageSquareText}
                            title="No hay plantillas todavía"
                            description="Creá tu primera plantilla desde el formulario."
                        />
                    )}

                    {!isLoading && data && data.data.length > 0 && (
                        <TemplatesTable
                            data={data}
                            onApprove={can('templates.update') ? onApproveTemplate : undefined}
                            approvePendingId={updateTemplate.isPending ? (updateTemplate.variables?.id ?? null) : null}
                            onDelete={can('templates.delete') ? setDeleting : undefined}
                            deletePendingId={deleteTemplate.isPending ? deleteTemplate.variables : null}
                            onPageChange={setPage}
                        />
                    )}
                </section>
            </div>

            <ConfirmDialog
                open={deleting !== null}
                title="Eliminar plantilla"
                description={`¿Eliminar la plantilla "${deleting?.name ?? ''}"? Esta acción no se puede deshacer.`}
                confirmLabel="Eliminar"
                destructive
                loading={deleteTemplate.isPending}
                onConfirm={confirmDelete}
                onCancel={() => setDeleting(null)}
            />
        </div>
    );
}
