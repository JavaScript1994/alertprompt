import { zodResolver } from '@hookform/resolvers/zod';
import { ArrowLeft, Lock } from 'lucide-react';
import { useEffect, useMemo } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { z } from 'zod';
import PageHeader from '@/components/shared/PageHeader';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/usePermissions';
import { useCreateRole, usePermissionTree, useRole, useUpdateRole } from '@/hooks/useRoles';
import { apiErrorMessage } from '@/lib/format';
import type { PermissionTreeSection, RoleScope } from '@/types';
import PermissionTree from './PermissionTree';
import { ROLE_SCOPE_LABELS } from './Roles';

const roleSchema = z.object({
    label: z.string().trim().min(1, 'Ingresa un nombre').max(60, 'Máximo 60 caracteres'),
    description: z.string().max(255, 'Máximo 255 caracteres'),
    scope: z.enum(['platform', 'client']),
    permissions: z.array(z.string()),
});

type RoleFormValues = z.infer<typeof roleSchema>;

/** Un rol de cliente solo ve las secciones de cliente; uno de plataforma, todo. */
function sectionsFor(tree: PermissionTreeSection[], scope: RoleScope): PermissionTreeSection[] {
    return scope === 'platform' ? tree : tree.filter((section) => section.scope === 'client');
}

export default function RoleEditor() {
    const params = useParams<{ id: string }>();
    const roleId = params.id ? Number(params.id) : null;
    const isNew = roleId === null;
    const navigate = useNavigate();
    const can = useCan();

    const { data: tree, isLoading: isTreeLoading } = usePermissionTree();
    const { data: role, isLoading: isRoleLoading } = useRole(roleId);
    const createRole = useCreateRole();
    const updateRole = useUpdateRole();
    const mutation = isNew ? createRole : updateRole;

    const readOnly = !can('admin.roles.manage') || (role?.is_locked ?? false);

    const {
        register,
        control,
        handleSubmit,
        watch,
        reset,
        setValue,
        getValues,
        formState: { errors, isDirty },
    } = useForm<RoleFormValues>({
        resolver: zodResolver(roleSchema),
        defaultValues: { label: '', description: '', scope: 'client', permissions: [] },
    });

    useEffect(() => {
        if (role) {
            reset({
                label: role.label,
                description: role.description ?? '',
                scope: role.scope,
                permissions: role.permissions ?? [],
            });
        }
    }, [role, reset]);

    const scope = watch('scope');
    const visibleSections = useMemo(() => sectionsFor(tree ?? [], scope), [tree, scope]);

    // Al pasar un rol nuevo a "Cliente" se descartan los permisos de
    // administración que ya no se pueden mostrar ni guardar.
    useEffect(() => {
        if (!tree) return;
        const allowed = new Set(sectionsFor(tree, scope).flatMap((s) => s.modules.flatMap((m) => m.permissions.map((p) => p.name))));
        const current = getValues('permissions');
        const kept = current.filter((name) => allowed.has(name));
        if (kept.length !== current.length) {
            setValue('permissions', kept, { shouldDirty: true });
        }
    }, [scope, tree, getValues, setValue]);

    const backToList = () => navigate('/admin/roles');

    const onSubmit = handleSubmit((values) => {
        const input = {
            label: values.label,
            description: values.description.trim() === '' ? null : values.description.trim(),
            permissions: values.permissions,
        };
        if (isNew) {
            createRole.mutate({ ...input, scope: values.scope }, { onSuccess: backToList });
        } else {
            updateRole.mutate({ ...input, id: roleId }, { onSuccess: backToList });
        }
    });

    const title = isNew ? 'Nuevo rol' : (role?.label ?? 'Rol');
    const selectedCount = watch('permissions').length;

    return (
        <div>
            <PageHeader
                title={title}
                description={
                    <Link to="/admin/roles" className="inline-flex items-center gap-1 hover:text-foreground">
                        <ArrowLeft className="size-3.5" />
                        Roles y permisos
                    </Link>
                }
            />

            {role?.is_locked && (
                <Alert variant="primary" className="mb-6">
                    <Lock />
                    <AlertTitle>Este rol no se edita</AlertTitle>
                    <AlertDescription>
                        El dueño de la plataforma siempre tiene todos los permisos, incluidos los que se agreguen en
                        el futuro.
                    </AlertDescription>
                </Alert>
            )}

            {(isTreeLoading || (!isNew && isRoleLoading)) ? (
                <Skeleton className="h-96 w-full rounded-xl" />
            ) : (
                <form onSubmit={onSubmit} noValidate className="grid grid-cols-1 gap-6 xl:grid-cols-12">
                    <Card className="h-fit xl:sticky xl:top-24 xl:col-span-4">
                        <CardHeader>
                            <CardTitle>Datos del rol</CardTitle>
                            <CardDescription>
                                {selectedCount} permiso{selectedCount === 1 ? '' : 's'} seleccionado
                                {selectedCount === 1 ? '' : 's'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <Field label="Nombre" htmlFor="role-label" error={errors.label?.message}>
                                <Input id="role-label" {...register('label')} disabled={readOnly} placeholder="Supervisor de campañas" />
                            </Field>

                            <Field label="Descripción" htmlFor="role-description" error={errors.description?.message}>
                                <Textarea
                                    id="role-description"
                                    rows={3}
                                    {...register('description')}
                                    disabled={readOnly}
                                    placeholder="Qué puede hacer quien tenga este rol."
                                />
                            </Field>

                            <Field
                                label="Panel"
                                htmlFor="role-scope"
                                hint={
                                    isNew
                                        ? 'Cliente: para usuarios de empresas y personas naturales. Plataforma: para tu equipo de AlertPrompt.'
                                        : 'El panel no cambia después de crear el rol.'
                                }
                            >
                                <Controller
                                    control={control}
                                    name="scope"
                                    render={({ field }) => (
                                        <Select value={field.value} onValueChange={(value) => value && field.onChange(value)} disabled={!isNew || readOnly}>
                                            <SelectTrigger id="role-scope" className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="client">{ROLE_SCOPE_LABELS.client}</SelectItem>
                                                <SelectItem value="platform">{ROLE_SCOPE_LABELS.platform}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    )}
                                />
                            </Field>

                            {mutation.isError && (
                                <Alert variant="error">
                                    <AlertTitle>
                                        {apiErrorMessage(mutation.error, ['label', 'permissions', 'scope', 'role'], 'No se pudo guardar el rol.')}
                                    </AlertTitle>
                                </Alert>
                            )}

                            {!readOnly && (
                                <div className="flex gap-2">
                                    <Button type="submit" className="flex-1" loading={mutation.isPending} disabled={!isNew && !isDirty}>
                                        {isNew ? 'Crear rol' : 'Guardar cambios'}
                                    </Button>
                                    <Button type="button" variant="outline" asChild>
                                        <Link to="/admin/roles">Cancelar</Link>
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <section className="xl:col-span-8">
                        <Controller
                            control={control}
                            name="permissions"
                            render={({ field }) => (
                                <PermissionTree
                                    sections={visibleSections}
                                    value={field.value}
                                    onChange={field.onChange}
                                    disabled={readOnly}
                                />
                            )}
                        />
                    </section>
                </form>
            )}
        </div>
    );
}
