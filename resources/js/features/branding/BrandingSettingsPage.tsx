import { zodResolver } from '@hookform/resolvers/zod';
import { Check, CheckCircle2, Copy, ExternalLink, ImageUp, Palette, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import BrandMark from '@/components/shared/BrandMark';
import EmptyState from '@/components/shared/EmptyState';
import PageHeader from '@/components/shared/PageHeader';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { useCan } from '@/hooks/usePermissions';
import { apiErrorMessage } from '@/lib/format';
import { applyServerErrors } from '@/lib/forms';
import { useBrandingSettings, useDeleteLogo, useUpdateBranding, useUploadLogo } from './api';
import { contrastWithWhite, HEX_COLOR, MIN_CONTRAST } from './contrast';
import type { BrandingSettings } from './types';

const schema = z.object({
    brand_color: z
        .string()
        .trim()
        .refine((value) => value === '' || HEX_COLOR.test(value), 'Usa un color en formato #RRGGBB')
        .refine((value) => value === '' || !HEX_COLOR.test(value) || contrastWithWhite(value) >= MIN_CONTRAST, 'Muy claro: el texto blanco de los botones no se leería'),
    brand_title: z.string().trim().max(80, 'Máximo 80 caracteres'),
});
type Form = z.infer<typeof schema>;

const LOGO_TYPES = ['image/png', 'image/jpeg', 'image/webp'];

/** Mini login con la marca, para ver el resultado antes de guardar. */
function LoginPreview({ name, logoUrl, color, title }: { name: string; logoUrl: string | null; color: string; title: string }) {
    return (
        <div className="overflow-hidden rounded-xl border bg-card shadow-sm" aria-label="Vista previa del login">
            <div className="flex items-center justify-between border-b px-4 py-3">
                {logoUrl ? (
                    <img src={logoUrl} alt="" className="h-7 w-auto max-w-[9rem] object-contain object-left" />
                ) : (
                    <span className="flex items-center gap-1.5 text-sm font-bold text-brand-700 dark:text-white">
                        <BrandMark className="size-6" />
                        AlertPrompt
                    </span>
                )}
                <span className="text-[0.65rem] text-muted-foreground">Centro de ayuda</span>
            </div>
            <div className="grid grid-cols-2 gap-3 p-4">
                <div className="rounded-lg bg-gradient-to-b from-slate-50 to-prompt-50 p-3 dark:from-white/5 dark:to-white/5">
                    <p className="text-sm leading-tight font-bold [overflow-wrap:anywhere] text-brand-700 dark:text-white">
                        {title || (
                            <>
                                Comunicaciones Masivas <span style={{ color }}>Sencillas.</span>
                            </>
                        )}
                    </p>
                    <div className="mt-3 h-14 rounded-md bg-white/70 dark:bg-white/10" />
                </div>
                <div className="space-y-2">
                    <p className="text-center text-xs font-bold text-brand-700 dark:text-white">Bienvenido de nuevo</p>
                    <p className="text-center text-[0.6rem] text-muted-foreground">Inicia sesión en {name}.</p>
                    <div className="h-5 rounded border" />
                    <div className="h-5 rounded border" />
                    <p className="text-right text-[0.6rem] font-semibold" style={{ color }}>
                        ¿Olvidaste tu contraseña?
                    </p>
                    <div className="flex h-6 items-center justify-center rounded text-[0.65rem] font-medium text-white" style={{ backgroundColor: color }}>
                        Iniciar sesión
                    </div>
                </div>
            </div>
            <p className="border-t px-4 py-2 text-center text-[0.6rem] text-muted-foreground">{name} · Con la tecnología de AlertPrompt</p>
        </div>
    );
}

function BrandingForm({ settings, canManage }: { settings: BrandingSettings; canManage: boolean }) {
    const update = useUpdateBranding();
    const upload = useUploadLogo();
    const removeLogo = useDeleteLogo();
    const fileInput = useRef<HTMLInputElement>(null);
    const [logoError, setLogoError] = useState<string | null>(null);
    const [copied, setCopied] = useState(false);

    const {
        register,
        handleSubmit,
        watch,
        setValue,
        setError,
        formState: { errors, isDirty },
    } = useForm<Form>({
        resolver: zodResolver(schema),
        // Valida mientras escribe: el aviso de contraste aparece junto con la vista previa.
        mode: 'onChange',
        values: { brand_color: settings.color ?? '', brand_title: settings.title ?? '' },
    });

    const colorValue = watch('brand_color');
    const previewColor = HEX_COLOR.test(colorValue) ? colorValue : settings.default_color;

    const submit = handleSubmit((values) =>
        update.mutate(
            { brand_color: values.brand_color || null, brand_title: values.brand_title || null },
            { onError: (error) => applyServerErrors(error, setError, ['brand_color', 'brand_title']) },
        ),
    );

    const pickLogo = (file: File | undefined) => {
        if (fileInput.current) fileInput.current.value = '';
        if (!file) return;
        if (!LOGO_TYPES.includes(file.type)) return setLogoError('Usa un logo PNG, JPG o WebP (SVG no está permitido).');
        if (file.size > 1024 * 1024) return setLogoError('El logo no puede pesar más de 1 MB.');
        setLogoError(null);
        upload.mutate(file);
    };

    const copyUrl = async () => {
        if (!settings.login_url) return;
        await navigator.clipboard.writeText(settings.login_url);
        setCopied(true);
    };

    const serverLogoError = upload.error ?? removeLogo.error;

    return (
        <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,26rem)]">
            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Dirección de tu login</CardTitle>
                        <CardDescription>Comparte este enlace con tu equipo. Ahí solo pueden entrar usuarios de {settings.name}.</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap items-center gap-2">
                        <code className="min-w-0 flex-1 truncate rounded-md bg-muted px-3 py-2 text-sm">{settings.login_url}</code>
                        <Button variant="outline" size="sm" onClick={copyUrl}>
                            {copied ? <Check /> : <Copy />}
                            {copied ? 'Copiado' : 'Copiar'}
                        </Button>
                        {settings.login_url && (
                            <Button variant="ghost" size="sm" asChild>
                                <a href={settings.login_url} target="_blank" rel="noreferrer">
                                    <ExternalLink />
                                    Abrir
                                </a>
                            </Button>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Logo</CardTitle>
                        <CardDescription>PNG, JPG o WebP de hasta 1 MB, idealmente horizontal y con fondo transparente.</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap items-center gap-4">
                        <div className="flex h-16 w-48 items-center justify-center rounded-lg border bg-muted/40 p-2">
                            {settings.logo_url ? (
                                <img src={settings.logo_url} alt={`Logo de ${settings.name}`} className="max-h-full max-w-full object-contain" />
                            ) : (
                                <span className="text-xs text-muted-foreground">Sin logo</span>
                            )}
                        </div>
                        {canManage && (
                            <div className="flex flex-wrap gap-2">
                                <Button variant="outline" size="sm" loading={upload.isPending} onClick={() => fileInput.current?.click()}>
                                    {!upload.isPending && <ImageUp />}
                                    {settings.logo_url ? 'Cambiar logo' : 'Subir logo'}
                                </Button>
                                {settings.logo_url && (
                                    <Button variant="ghosterror" size="sm" loading={removeLogo.isPending} onClick={() => removeLogo.mutate()}>
                                        {!removeLogo.isPending && <Trash2 />}
                                        Quitar
                                    </Button>
                                )}
                                <input
                                    ref={fileInput}
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    className="sr-only"
                                    aria-label="Logo"
                                    onChange={(event) => pickLogo(event.target.files?.[0])}
                                />
                            </div>
                        )}
                        {(logoError ?? serverLogoError) && (
                            <p className="w-full text-sm text-error">{logoError ?? apiErrorMessage(serverLogoError, ['logo'], 'No se pudo guardar el logo.')}</p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Color y bienvenida</CardTitle>
                        <CardDescription>Se aplican a los botones y enlaces del login. Vacío = los de AlertPrompt.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4" noValidate>
                            {update.isSuccess && !isDirty && (
                                <Alert variant="success">
                                    <CheckCircle2 />
                                    <AlertTitle>Marca guardada.</AlertTitle>
                                </Alert>
                            )}
                            <Field label="Color principal" htmlFor="brand-color" error={errors.brand_color?.message} hint="Debe ser lo bastante oscuro para que el texto blanco se lea (contraste WCAG AA).">
                                <div className="flex gap-2">
                                    <input
                                        type="color"
                                        aria-label="Elegir color"
                                        value={previewColor}
                                        disabled={!canManage}
                                        onChange={(event) => setValue('brand_color', event.target.value, { shouldDirty: true, shouldValidate: true })}
                                        className="h-10 w-12 shrink-0 cursor-pointer rounded-md border bg-transparent p-1"
                                    />
                                    <Input id="brand-color" placeholder={settings.default_color} disabled={!canManage} className="font-mono uppercase" {...register('brand_color')} />
                                </div>
                            </Field>
                            <Field label="Título de bienvenida" htmlFor="brand-title" error={errors.brand_title?.message} hint="Reemplaza «Comunicaciones Masivas Sencillas.» en el panel izquierdo.">
                                <Input id="brand-title" placeholder="Ej.: Bienvenido al centro de mensajes de Andina" disabled={!canManage} {...register('brand_title')} />
                            </Field>
                            {canManage && (
                                <div className="flex justify-end">
                                    <Button type="submit" loading={update.isPending} disabled={!isDirty}>
                                        Guardar marca
                                    </Button>
                                </div>
                            )}
                        </form>
                    </CardContent>
                </Card>
            </div>

            <div className="xl:sticky xl:top-6 xl:self-start">
                <p className="mb-2 text-sm font-medium text-foreground">Vista previa</p>
                <LoginPreview name={settings.name} logoUrl={settings.logo_url} color={previewColor} title={watch('brand_title')} />
            </div>
        </div>
    );
}

/** Configuración → Marca: logo, color y título del login de la empresa. */
export default function BrandingSettingsPage() {
    const { data: settings, isLoading, isError } = useBrandingSettings();
    const canManage = useCan()('settings.manage');

    return (
        <div>
            <PageHeader title="Marca" description="Personaliza el login de tu empresa con tu logo y tus colores." />
            {isError ? (
                <EmptyState
                    icon={Palette}
                    title="Tu plan no incluye la marca en el login"
                    description="Está disponible en los planes Avanzado y Empresarial. Puedes solicitar el cambio desde Membresía."
                />
            ) : isLoading || !settings ? (
                <Skeleton className="h-96 w-full rounded-xl" />
            ) : (
                <BrandingForm settings={settings} canManage={canManage} />
            )}
        </div>
    );
}
