import { zodResolver } from '@hookform/resolvers/zod';
import { Mail, Search, XCircle } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { z } from 'zod';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { usePlans } from '@/hooks/useMemberships';
import { applyServerErrors } from '@/lib/forms';
import { cn } from '@/lib/utils';
import { useClientOptions, useSaveAnnouncement } from './api';
import { ANNOUNCEMENT_TYPES, TYPE_ORDER } from './meta';
import type { AdminAnnouncement, AnnouncementAudience, AnnouncementType } from './types';

const schema = z
    .object({
        type: z.enum(['feature', 'notice', 'incident', 'action_required', 'promotion']),
        title: z.string().trim().min(1, 'Escribe un título').max(120),
        body: z.string().trim().min(1, 'Escribe el mensaje').max(2000),
        link_url: z
            .string()
            .trim()
            .refine((value) => value === '' || /^(\/[\w/?=&.-]*|https:\/\/\S+)$/.test(value), 'Usa una ruta del panel (/settings/membership) o https://'),
        link_label: z.string().trim().max(40),
        audience: z.enum(['all', 'plans', 'tenants']),
        plan_keys: z.array(z.string()),
        tenant_ids: z.array(z.number()),
        admins_only: z.boolean(),
        publish: z.enum(['draft', 'now', 'schedule']),
        published_at: z.string(),
        expires_at: z.string(),
        send_email: z.boolean(),
    })
    .superRefine((value, ctx) => {
        if (value.audience === 'plans' && value.plan_keys.length === 0) ctx.addIssue({ code: 'custom', path: ['plan_keys'], message: 'Elige al menos un plan' });
        if (value.audience === 'tenants' && value.tenant_ids.length === 0) ctx.addIssue({ code: 'custom', path: ['tenant_ids'], message: 'Elige al menos un cliente' });
        if (value.publish === 'schedule' && !value.published_at) ctx.addIssue({ code: 'custom', path: ['published_at'], message: 'Elige fecha y hora' });
        if (value.link_url && !value.link_label) ctx.addIssue({ code: 'custom', path: ['link_label'], message: 'Escribe el texto del botón' });
    });

type Form = z.infer<typeof schema>;

const AUDIENCE_LABELS: Record<AnnouncementAudience, string> = { all: 'Todos los clientes', plans: 'Por plan', tenants: 'Clientes específicos' };
const PUBLISH_LABELS: Record<Form['publish'], string> = { now: 'Publicar ahora', schedule: 'Programar', draft: 'Guardar borrador' };

const FIELDS = ['type', 'title', 'body', 'link_url', 'link_label', 'plan_keys', 'tenant_ids', 'published_at', 'expires_at', 'send_email'] as const;

/** ISO → valor de <input type="datetime-local"> en hora local. */
function toLocalInput(iso: string | null): string {
    if (!iso) return '';
    const date = new Date(iso);
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
}

function defaults(item?: AdminAnnouncement): Form {
    const scheduled = item?.published_at && new Date(item.published_at) > new Date();
    return {
        type: item?.type ?? 'feature',
        title: item?.title ?? '',
        body: item?.body ?? '',
        link_url: item?.link_url ?? '',
        link_label: item?.link_label ?? '',
        audience: item?.audience ?? 'all',
        plan_keys: item?.plan_keys ?? [],
        tenant_ids: item?.tenants.map((tenant) => tenant.id) ?? [],
        admins_only: item?.admins_only ?? false,
        publish: !item ? 'now' : !item.published_at ? 'draft' : scheduled ? 'schedule' : 'now',
        published_at: toLocalInput(item?.published_at ?? null),
        expires_at: toLocalInput(item?.expires_at ?? null),
        send_email: item?.send_email ?? false,
    };
}

/** Grupo de opciones con radios nativos (accesible con teclado), con aspecto de chips o tarjetas. */
function ChoiceGroup<T extends string>({
    name,
    legend,
    value,
    onChange,
    options,
    className,
    itemClassName,
    render,
}: {
    name: string;
    legend: string;
    value: T;
    onChange: (value: T) => void;
    options: readonly T[];
    className?: string;
    itemClassName: string;
    render: (option: T) => ReactNode;
}) {
    return (
        <fieldset className={className}>
            <legend className="sr-only">{legend}</legend>
            {options.map((option) => (
                <label
                    key={option}
                    className={cn(
                        itemClassName,
                        'cursor-pointer has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring',
                        value === option ? 'border-primary bg-lightprimary font-medium text-foreground dark:border-brand-200' : 'text-muted-foreground hover:bg-muted',
                    )}
                >
                    <input type="radio" name={name} value={option} checked={value === option} onChange={() => onChange(option)} className="sr-only" />
                    {render(option)}
                </label>
            ))}
        </fieldset>
    );
}

/** Cómo lo verá el cliente. */
function Preview({ type, title, body, linkLabel }: { type: AnnouncementType; title: string; body: string; linkLabel: string }) {
    const meta = ANNOUNCEMENT_TYPES[type];
    const banner = type === 'incident' || type === 'action_required';

    return (
        <div className="space-y-3">
            <p className="text-sm font-medium text-foreground">Vista previa</p>
            {banner && (
                <div className={cn('flex items-center gap-2 rounded-lg px-3 py-2 text-xs', type === 'incident' ? 'bg-lighterror' : 'bg-lightwarning')}>
                    <meta.icon className={cn('size-4 shrink-0', type === 'incident' ? 'text-error' : 'text-warning')} />
                    <p className="min-w-0 flex-1 truncate">
                        <span className="font-semibold">{title || 'Título'}.</span> <span className="text-muted-foreground">{body.split('\n')[0] || 'Mensaje'}</span>
                    </p>
                </div>
            )}
            <div className="rounded-xl border bg-card p-4 shadow-sm">
                <div className="flex items-start gap-3">
                    <span className={cn('flex size-9 shrink-0 items-center justify-center rounded-full', meta.tone)}>
                        <meta.icon className="size-4" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <Badge variant={meta.badge}>{meta.label}</Badge>
                        <p className="mt-1.5 font-semibold [overflow-wrap:anywhere] text-foreground">{title || 'Título del comunicado'}</p>
                        <p className="mt-1 text-sm whitespace-pre-line [overflow-wrap:anywhere] text-muted-foreground">{body || 'Aquí va el mensaje.'}</p>
                        {linkLabel && (
                            <span className="mt-3 inline-flex rounded-md border px-3 py-1.5 text-xs font-medium">{linkLabel} →</span>
                        )}
                    </div>
                </div>
            </div>
            <p className="text-xs text-muted-foreground">{meta.description}</p>
        </div>
    );
}

function ClientPicker({ value, onChange }: { value: number[]; onChange: (ids: number[]) => void }) {
    const [search, setSearch] = useState('');
    const { data: clients } = useClientOptions(search);

    return (
        <div className="space-y-2 rounded-lg border p-3">
            <div className="relative">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Buscar por nombre o RUC" className="pl-9" aria-label="Buscar clientes" />
            </div>
            <ul className="max-h-40 space-y-1 overflow-y-auto">
                {(clients ?? []).map((client) => {
                    const checked = value.includes(client.id);
                    return (
                        <li key={client.id} className="flex items-center gap-2.5 rounded px-1 py-1 hover:bg-muted">
                            <Checkbox
                                id={`client-${client.id}`}
                                checked={checked}
                                onCheckedChange={(state) => onChange(state === true ? [...value, client.id] : value.filter((id) => id !== client.id))}
                            />
                            <Label htmlFor={`client-${client.id}`} className="flex-1 cursor-pointer font-normal">
                                {client.name}
                            </Label>
                            <span className="text-xs text-muted-foreground">{client.plan_name}</span>
                        </li>
                    );
                })}
                {clients?.length === 0 && <li className="py-2 text-center text-sm text-muted-foreground">Sin resultados.</li>}
            </ul>
            <p className="text-xs text-muted-foreground">{value.length === 1 ? '1 cliente elegido' : `${value.length} clientes elegidos`}</p>
        </div>
    );
}

export default function AnnouncementEditor({ item, onClose }: { item?: AdminAnnouncement; onClose: () => void }) {
    const save = useSaveAnnouncement(item?.id);
    const { data: plans } = usePlans();
    const [generalError, setGeneralError] = useState<string | null>(null);

    const {
        register,
        control,
        handleSubmit,
        watch,
        setError,
        formState: { errors },
    } = useForm<Form>({ resolver: zodResolver(schema), defaultValues: defaults(item) });

    const type = watch('type');
    const audience = watch('audience');
    const publish = watch('publish');
    const emailed = item?.emailed_at !== null && item?.emailed_at !== undefined;

    const submit = handleSubmit((values) => {
        setGeneralError(null);
        save.mutate(
            {
                type: values.type,
                title: values.title,
                body: values.body,
                link_url: values.link_url || null,
                link_label: values.link_url ? values.link_label : null,
                audience: values.audience,
                plan_keys: values.plan_keys,
                tenant_ids: values.tenant_ids,
                admins_only: values.admins_only,
                published_at:
                    values.publish === 'draft'
                        ? null
                        : values.publish === 'schedule'
                          ? new Date(values.published_at).toISOString()
                          : (item?.published_at && new Date(item.published_at) <= new Date() ? item.published_at : new Date().toISOString()),
                expires_at: values.expires_at ? new Date(values.expires_at).toISOString() : null,
                send_email: ANNOUNCEMENT_TYPES[values.type].canEmail && values.send_email && !emailed,
            },
            { onSuccess: onClose, onError: (error) => setGeneralError(applyServerErrors(error, setError, FIELDS)) },
        );
    });

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[92vh] max-w-5xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{item ? 'Editar comunicado' : 'Nuevo comunicado'}</DialogTitle>
                    <DialogDescription>Lo verán los usuarios de los clientes que elijas, dentro del panel.</DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-5" noValidate>
                    {generalError && (
                        <Alert variant="error">
                            <XCircle />
                            <AlertTitle>{generalError}</AlertTitle>
                        </Alert>
                    )}

                    <div className="grid grid-cols-1 gap-x-8 gap-y-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                        <div className="space-y-5">
                            <Field label="Tipo" error={errors.type?.message}>
                                <Controller
                                    control={control}
                                    name="type"
                                    render={({ field }) => (
                                        <ChoiceGroup
                                            name="announcement-type"
                                            legend="Tipo"
                                            value={field.value}
                                            onChange={field.onChange}
                                            options={TYPE_ORDER}
                                            className="grid grid-cols-2 gap-2 sm:grid-cols-5"
                                            itemClassName="flex flex-col items-center gap-1.5 rounded-lg border px-2 py-2.5 text-xs font-medium transition-colors"
                                            render={(key) => {
                                                const meta = ANNOUNCEMENT_TYPES[key];
                                                return (
                                                    <>
                                                        <span className={cn('flex size-7 items-center justify-center rounded-full', meta.tone)}>
                                                            <meta.icon className="size-3.5" />
                                                        </span>
                                                        {meta.label}
                                                    </>
                                                );
                                            }}
                                        />
                                    )}
                                />
                            </Field>

                            <Field label="Título" htmlFor="announcement-title" error={errors.title?.message}>
                                <Input id="announcement-title" maxLength={120} {...register('title')} />
                            </Field>
                            <Field label="Mensaje" htmlFor="announcement-body" error={errors.body?.message} hint="Texto simple. Deja una línea en blanco para separar párrafos.">
                                <Textarea id="announcement-body" rows={4} maxLength={2000} {...register('body')} />
                            </Field>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-[minmax(0,1fr)_12rem]">
                                <Field label="Enlace (opcional)" htmlFor="announcement-link" error={errors.link_url?.message} hint="Ruta del panel o https://">
                                    <Input id="announcement-link" placeholder="/settings/membership" {...register('link_url')} />
                                </Field>
                                <Field label="Texto del botón" htmlFor="announcement-link-label" error={errors.link_label?.message}>
                                    <Input id="announcement-link-label" placeholder="Ver planes" maxLength={40} {...register('link_label')} />
                                </Field>
                            </div>

                            <fieldset className="space-y-3">
                                <legend className="text-sm font-medium text-foreground">Destinatarios</legend>
                                <Controller
                                    control={control}
                                    name="audience"
                                    render={({ field }) => (
                                        <ChoiceGroup
                                            name="announcement-audience"
                                            legend="Destinatarios"
                                            value={field.value}
                                            onChange={field.onChange}
                                            options={['all', 'plans', 'tenants'] as const}
                                            className="flex flex-wrap gap-2"
                                            itemClassName="rounded-full border px-3.5 py-1.5 text-sm transition-colors"
                                            render={(key: AnnouncementAudience) => AUDIENCE_LABELS[key]}
                                        />
                                    )}
                                />
                                {audience === 'plans' && (
                                    <Controller
                                        control={control}
                                        name="plan_keys"
                                        render={({ field }) => (
                                            <div className="flex flex-wrap gap-x-5 gap-y-2">
                                                {(plans ?? []).map((plan) => (
                                                    <div key={plan.key} className="flex items-center gap-2">
                                                        <Checkbox
                                                            id={`plan-${plan.key}`}
                                                            checked={field.value.includes(plan.key)}
                                                            onCheckedChange={(state) =>
                                                                field.onChange(state === true ? [...field.value, plan.key] : field.value.filter((key) => key !== plan.key))
                                                            }
                                                        />
                                                        <Label htmlFor={`plan-${plan.key}`} className="cursor-pointer font-normal">
                                                            {plan.name}
                                                        </Label>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    />
                                )}
                                {audience === 'tenants' && (
                                    <Controller control={control} name="tenant_ids" render={({ field }) => <ClientPicker value={field.value} onChange={field.onChange} />} />
                                )}
                                {(errors.plan_keys?.message ?? errors.tenant_ids?.message) && (
                                    <p className="text-sm text-error">{errors.plan_keys?.message ?? errors.tenant_ids?.message}</p>
                                )}
                                <Controller
                                    control={control}
                                    name="admins_only"
                                    render={({ field }) => (
                                        <div className="flex items-center gap-2.5">
                                            <Checkbox id="admins-only" checked={field.value} onCheckedChange={(state) => field.onChange(state === true)} />
                                            <Label htmlFor="admins-only" className="cursor-pointer font-normal">
                                                Solo administradores de cada cliente
                                            </Label>
                                        </div>
                                    )}
                                />
                            </fieldset>

                            <fieldset className="space-y-3">
                                <legend className="text-sm font-medium text-foreground">Publicación</legend>
                                <Controller
                                    control={control}
                                    name="publish"
                                    render={({ field }) => (
                                        <ChoiceGroup
                                            name="announcement-publish"
                                            legend="Publicación"
                                            value={field.value}
                                            onChange={field.onChange}
                                            options={['now', 'schedule', 'draft'] as const}
                                            className="flex flex-wrap gap-2"
                                            itemClassName="rounded-full border px-3.5 py-1.5 text-sm transition-colors"
                                            render={(key) => PUBLISH_LABELS[key]}
                                        />
                                    )}
                                />
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    {publish === 'schedule' && (
                                        <Field label="Fecha y hora" htmlFor="announcement-published-at" error={errors.published_at?.message}>
                                            <Input id="announcement-published-at" type="datetime-local" {...register('published_at')} />
                                        </Field>
                                    )}
                                    <Field label="Vence (opcional)" htmlFor="announcement-expires-at" error={errors.expires_at?.message} hint="Después deja de mostrarse.">
                                        <Input id="announcement-expires-at" type="datetime-local" {...register('expires_at')} />
                                    </Field>
                                </div>
                            </fieldset>

                            {ANNOUNCEMENT_TYPES[type].canEmail && (
                                <Controller
                                    control={control}
                                    name="send_email"
                                    render={({ field }) => (
                                        <div className="flex items-start gap-2.5 rounded-lg border p-3">
                                            <Checkbox id="send-email" className="mt-0.5" checked={field.value && !emailed} disabled={emailed} onCheckedChange={(state) => field.onChange(state === true)} />
                                            <Label htmlFor="send-email" className="cursor-pointer flex-col items-start gap-0.5 font-normal">
                                                <span className="inline-flex items-center gap-1.5 font-medium text-foreground">
                                                    <Mail className="size-4" />
                                                    También enviar por correo
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {emailed ? 'Ya se envió por correo.' : 'Se envía una sola vez, al publicarse, a los destinatarios.'}
                                                </span>
                                            </Label>
                                        </div>
                                    )}
                                />
                            )}
                        </div>

                        <div className="lg:sticky lg:top-0 lg:self-start">
                            <Preview type={type} title={watch('title')} body={watch('body')} linkLabel={watch('link_url') ? watch('link_label') : ''} />
                        </div>
                    </div>

                    <DialogFooter className="border-t pt-4">
                        <Button type="button" variant="outline" onClick={onClose}>
                            Cancelar
                        </Button>
                        <Button type="submit" loading={save.isPending}>
                            {publish === 'draft' ? 'Guardar borrador' : publish === 'schedule' ? 'Programar' : item ? 'Guardar' : 'Publicar'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
