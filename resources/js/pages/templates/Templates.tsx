import { zodResolver } from '@hookform/resolvers/zod';
import { AxiosError } from 'axios';
import { CheckCircle2, MessageSquareText } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import Alert from '@/components/ui/Alert';
import Badge, { type BadgeVariant } from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import EmptyState from '@/components/ui/EmptyState';
import Input from '@/components/ui/Input';
import PageHeader from '@/components/ui/PageHeader';
import Select from '@/components/ui/Select';
import { SkeletonTable } from '@/components/ui/Skeleton';
import Textarea from '@/components/ui/Textarea';
import {
    useCreateTemplate,
    useDeleteTemplate,
    usePreviewTemplate,
    useTemplates,
    useUpdateTemplate,
} from '@/hooks/useTemplates';
import type { Template, TemplateStatus } from '@/types';
import TemplatesTable from './TemplatesTable';

const STATUS_LABELS: Record<TemplateStatus, string> = {
    draft: 'Borrador',
    pending_approval: 'Pendiente',
    approved: 'Aprobada',
    rejected: 'Rechazada',
    disabled: 'Deshabilitada',
};

const STATUS_VARIANTS: Record<TemplateStatus, BadgeVariant> = {
    draft: 'neutral',
    pending_approval: 'warning',
    approved: 'success',
    rejected: 'error',
    disabled: 'neutral',
};

const templateSchema = z.object({
    channel: z.enum(['whatsapp', 'sms', 'email']),
    category: z.enum(['marketing', 'utility', 'authentication']),
    name: z.string().min(1, 'Ingresa un nombre').max(255),
    body: z.string().min(1, 'Ingresa el contenido del mensaje').max(4096),
});

type TemplateFormValues = z.infer<typeof templateSchema>;

export default function Templates() {
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

    const onDeleteTemplate = (template: Template) => {
        if (window.confirm(`¿Eliminar la plantilla "${template.name}"? Esta acción no se puede deshacer.`)) {
            deleteTemplate.mutate(template.id);
        }
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
        handleSubmit,
        watch,
        reset,
        formState: { errors },
    } = useForm<TemplateFormValues>({
        resolver: zodResolver(templateSchema),
        defaultValues: { channel: 'whatsapp', category: 'marketing', name: '', body: '' },
    });

    const body = watch('body');

    // Preview en vivo: debounced para no pegarle al backend en cada tecla.
    // El parseo de variables vive solo en TemplateRenderer (backend) — no lo
    // duplicamos en el cliente.
    useEffect(() => {
        if (!body) return;
        const timeout = setTimeout(() => {
            preview.mutate({ body, sampleData });
            // eslint-disable-next-line react-hooks/exhaustive-deps
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

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <section>
                    <h2 className="mb-3 text-sm font-semibold text-ink-900">Nueva plantilla</h2>

                    <form
                        onSubmit={onSubmit}
                        className="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <div className="grid grid-cols-2 gap-4">
                            <Select label="Canal" {...register('channel')}>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="sms">SMS</option>
                                <option value="email">Email</option>
                            </Select>
                            <Select label="Categoría" {...register('category')}>
                                <option value="marketing">Marketing</option>
                                <option value="utility">Utility</option>
                                <option value="authentication">Authentication</option>
                            </Select>
                        </div>

                        <Input
                            label="Nombre"
                            placeholder="bienvenida_nuevo_cliente"
                            error={errors.name?.message}
                            {...register('name')}
                        />

                        <Textarea
                            label={
                                <>
                                    Contenido{' '}
                                    <span className="font-normal text-slate-400">
                                        — usa {'{{variable}}'} para campos dinámicos
                                    </span>
                                </>
                            }
                            rows={5}
                            className="font-mono"
                            placeholder="Hola {{nombre}}, tu pedido {{pedido}} está listo."
                            error={errors.body?.message}
                            {...register('body')}
                        />

                        {createTemplate.isError && (
                            <Alert type="error">
                                {createTemplate.error instanceof AxiosError
                                    ? (createTemplate.error.response?.data?.errors?.name?.[0] ??
                                      createTemplate.error.response?.data?.errors?.channel?.[0] ??
                                      createTemplate.error.response?.data?.errors?.category?.[0] ??
                                      createTemplate.error.response?.data?.errors?.body?.[0] ??
                                      createTemplate.error.response?.data?.message ??
                                      'No se pudo crear la plantilla.')
                                    : 'No se pudo crear la plantilla.'}
                            </Alert>
                        )}

                        <Button type="submit" loading={createTemplate.isPending} className="w-full">
                            {createTemplate.isPending ? 'Guardando…' : 'Crear plantilla'}
                        </Button>
                    </form>

                    {justCreated && (
                        <div className="mt-4 rounded-xl border border-slate-200 bg-white p-5">
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <p className="text-sm font-medium text-ink-900">{justCreated.name}</p>
                                    <p className="mt-0.5 text-xs text-slate-500">
                                        Estado actual de la plantilla que acabás de crear.
                                    </p>
                                </div>
                                <Badge variant={STATUS_VARIANTS[justCreated.status]}>
                                    {STATUS_LABELS[justCreated.status]}
                                </Badge>
                            </div>

                            {justCreated.status === 'approved' ? (
                                <p className="mt-3 flex items-center gap-1.5 text-xs text-whatsapp-700">
                                    <CheckCircle2 className="h-3.5 w-3.5" />
                                    Ya está aprobada — podés usarla para lanzar una campaña.
                                </p>
                            ) : (
                                <>
                                    <p className="mt-3 text-xs text-slate-500">
                                        Las plantillas nuevas quedan en <strong>Borrador</strong>. Este MVP no tiene
                                        un flujo automático de revisión (en producción, WhatsApp exige que Meta
                                        apruebe cada plantilla) — para poder usarla en una campaña, alguien tiene
                                        que aprobarla manualmente.
                                    </p>
                                    <Button
                                        size="sm"
                                        variant="success"
                                        className="mt-3"
                                        onClick={() => onApproveTemplate(justCreated)}
                                        loading={updateTemplate.isPending}
                                    >
                                        Aprobar ahora
                                    </Button>
                                </>
                            )}
                        </div>
                    )}

                    {preview.data && preview.data.variables.length > 0 && (
                        <div className="mt-4 rounded-xl border border-slate-200 bg-white p-5">
                            <p className="mb-2 text-sm font-medium text-slate-700">Datos de prueba</p>
                            <div className="space-y-2">
                                {preview.data.variables.map((variable) => (
                                    <div key={variable} className="flex items-center gap-2">
                                        <label className="w-28 shrink-0 text-sm text-slate-600">{variable}</label>
                                        <input
                                            value={sampleData[variable] ?? ''}
                                            onChange={(event) =>
                                                setSampleData((prev) => ({ ...prev, [variable]: event.target.value }))
                                            }
                                            className="flex-1 rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none"
                                        />
                                    </div>
                                ))}
                            </div>

                            <p className="mt-4 mb-1.5 text-sm font-medium text-slate-700">Vista previa</p>
                            <p className="rounded-lg bg-slate-50 p-3 text-sm whitespace-pre-wrap text-slate-800">
                                {preview.data.rendered}
                            </p>
                            {preview.data.missing.length > 0 && (
                                <p className="mt-2 text-xs text-amber-600">
                                    Faltan valores para: {preview.data.missing.join(', ')}
                                </p>
                            )}
                        </div>
                    )}
                </section>

                <section>
                    <h2 className="mb-3 text-sm font-semibold text-ink-900">Todas las plantillas</h2>

                    {deleteTemplate.isError && (
                        <Alert type="error" className="mb-3">
                            {deleteTemplate.error instanceof AxiosError
                                ? (deleteTemplate.error.response?.data?.message ??
                                  'No se pudo eliminar la plantilla.')
                                : 'No se pudo eliminar la plantilla.'}
                        </Alert>
                    )}
                    {updateTemplate.isError && (
                        <Alert type="error" className="mb-3">
                            {updateTemplate.error instanceof AxiosError
                                ? (updateTemplate.error.response?.data?.message ??
                                  'No se pudo aprobar la plantilla.')
                                : 'No se pudo aprobar la plantilla.'}
                        </Alert>
                    )}

                    {isLoading && (
                        <div className="rounded-xl border border-slate-200 bg-white p-5">
                            <SkeletonTable rows={5} />
                        </div>
                    )}

                    {!isLoading && data?.data.length === 0 && (
                        <EmptyState
                            icon={MessageSquareText}
                            title="No hay plantillas todavía"
                            description="Creá tu primera plantilla desde el formulario de la izquierda."
                        />
                    )}

                    {!isLoading && data && data.data.length > 0 && (
                        <TemplatesTable
                            data={data}
                            onApprove={onApproveTemplate}
                            approvePendingId={updateTemplate.isPending ? (updateTemplate.variables?.id ?? null) : null}
                            onDelete={onDeleteTemplate}
                            deletePendingId={deleteTemplate.isPending ? deleteTemplate.variables : null}
                            onPageChange={setPage}
                        />
                    )}
                </section>
            </div>
        </div>
    );
}
