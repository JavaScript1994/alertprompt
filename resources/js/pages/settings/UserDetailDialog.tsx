import { zodResolver } from '@hookform/resolvers/zod';
import { CheckCircle2, Clock, Mail, ShieldCheck, ShieldOff } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link } from 'react-router-dom';
import { z } from 'zod';
import ConfirmDialog from '@/components/shared/ConfirmDialog';
import Tabs from '@/components/shared/Tabs';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useResetUserMfa } from '@/features/mfa/api';
import {
    useChangeUserEmail,
    useDeleteUserPhoto,
    useUpdateUserPersonalData,
    useUpdateUserRole,
    useUploadUserPhoto,
} from '@/features/profile/api';
import PersonalDataFields, { PersonalDataNotice } from '@/features/profile/PersonalDataFields';
import PhotoField from '@/features/profile/PhotoField';
import {
    PERSONAL_KEYS,
    personalDataSchema,
    personalDefaults,
    type PersonalDataInput,
    type PersonalDataOutput,
} from '@/features/profile/schemas';
import UserAvatar from '@/features/profile/UserAvatar';
import { useAuthUser } from '@/hooks/useAuth';
import { useAssignableRoles, useTenantUsers } from '@/hooks/useUsers';
import { apiErrorMessage, formatDateTime } from '@/lib/format';
import { applyServerErrors } from '@/lib/forms';
import type { TenantUser } from '@/types';

type TabKey = 'data' | 'access' | 'security';

function Saved({ show }: { show: boolean }) {
    if (!show) return null;
    return (
        <Alert variant="success">
            <CheckCircle2 />
            <AlertTitle>Cambios guardados.</AlertTitle>
        </Alert>
    );
}

function PersonalDataTab({ user }: { user: TenantUser }) {
    const update = useUpdateUserPersonalData(user.id);
    const upload = useUploadUserPhoto(user.id);
    const removePhoto = useDeleteUserPhoto(user.id);
    const [saved, setSaved] = useState(false);
    const [generalError, setGeneralError] = useState<string | null>(null);

    const {
        register,
        handleSubmit,
        setError,
        formState: { errors, isDirty },
    } = useForm<PersonalDataInput, unknown, PersonalDataOutput>({
        resolver: zodResolver(personalDataSchema),
        values: personalDefaults(user),
    });

    const submit = handleSubmit((values) => {
        setSaved(false);
        setGeneralError(null);
        update.mutate(values, {
            onSuccess: () => setSaved(true),
            onError: (error) => setGeneralError(applyServerErrors(error, setError, PERSONAL_KEYS)),
        });
    });

    const photoError = upload.error ?? removePhoto.error;

    return (
        <div className="grid grid-cols-1 gap-6 md:grid-cols-[13rem_minmax(0,1fr)]">
            <div className="space-y-3 md:border-r md:pr-6">
                <p className="text-sm font-medium text-foreground">Foto</p>
                <PhotoField
                    layout="stacked"
                    name={user.name}
                    photoUrl={user.photo_url}
                    busy={upload.isPending || removePhoto.isPending}
                    onUpload={(file) => upload.mutate(file)}
                    onRemove={() => removePhoto.mutate()}
                    error={photoError ? apiErrorMessage(photoError, ['photo'], 'No se pudo guardar la foto.') : null}
                />
            </div>
            <form onSubmit={submit} className="space-y-4" noValidate>
                <Saved show={saved && !isDirty} />
                {generalError && (
                    <Alert variant="error">
                        <AlertTitle>{generalError}</AlertTitle>
                    </Alert>
                )}
                <PersonalDataFields
                    idPrefix="user"
                    register={(key) => register(key)}
                    errors={Object.fromEntries(PERSONAL_KEYS.map((key) => [key, errors[key]?.message]))}
                />
                <PersonalDataNotice />
                <div className="flex justify-end">
                    <Button type="submit" loading={update.isPending} disabled={!isDirty}>
                        Guardar datos
                    </Button>
                </div>
            </form>
        </div>
    );
}

const emailSchema = z.object({ email: z.string().trim().min(1, 'Ingresa el correo').email('Correo inválido') });

function EmailSection({ user }: { user: TenantUser }) {
    const change = useChangeUserEmail(user.id);
    const [editing, setEditing] = useState(false);
    const {
        register,
        handleSubmit,
        setError,
        formState: { errors },
    } = useForm<z.infer<typeof emailSchema>>({ resolver: zodResolver(emailSchema), defaultValues: { email: '' } });

    const submit = handleSubmit(({ email }) =>
        change.mutate(email, {
            onSuccess: () => setEditing(false),
            onError: (error) => applyServerErrors(error, setError, ['email']),
        }),
    );

    return (
        <div className="space-y-3 rounded-lg border p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="text-sm font-medium text-foreground">Correo de inicio de sesión</p>
                    <p className="text-sm text-muted-foreground">{user.email}</p>
                </div>
                {!editing && (
                    <Button variant="outline" size="sm" onClick={() => setEditing(true)}>
                        <Mail />
                        Cambiar correo
                    </Button>
                )}
            </div>
            {user.pending_email && !editing && (
                <Alert variant="warning">
                    <Clock />
                    <AlertTitle>Esperando confirmación de {user.pending_email}</AlertTitle>
                    <AlertDescription>Hasta que confirme desde ese correo, sigue entrando con el actual.</AlertDescription>
                </Alert>
            )}
            {editing && (
                <form onSubmit={submit} className="flex flex-wrap items-start gap-2" noValidate>
                    <Field htmlFor="user-new-email" error={errors.email?.message} className="min-w-[16rem] flex-1">
                        <Input id="user-new-email" type="email" placeholder="nuevo@empresa.com" aria-label="Correo nuevo" {...register('email')} />
                    </Field>
                    <Button type="submit" loading={change.isPending}>
                        Enviar confirmación
                    </Button>
                    <Button type="button" variant="ghost" onClick={() => setEditing(false)}>
                        Cancelar
                    </Button>
                    <p className="w-full text-xs text-muted-foreground">
                        Enviaremos un enlace al correo nuevo. El cambio se aplica cuando la persona lo confirme.
                    </p>
                </form>
            )}
        </div>
    );
}

function AccessTab({ user, isMe }: { user: TenantUser; isMe: boolean }) {
    const { data: roles } = useAssignableRoles();
    const update = useUpdateUserRole(user.id);
    const [roleId, setRoleId] = useState(user.roles[0] ? String(user.roles[0].id) : '');
    const selected = roles?.find((role) => String(role.id) === roleId);
    const changed = roleId !== (user.roles[0] ? String(user.roles[0].id) : '');

    return (
        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
            <div className="space-y-4">
                <Field
                    label="Rol"
                    htmlFor="user-role"
                    hint={selected?.description ?? undefined}
                    error={update.isError ? apiErrorMessage(update.error, ['role_id'], 'No se pudo cambiar el rol.') : null}
                >
                    <Select value={roleId} onValueChange={(value) => value && setRoleId(value)}>
                        <SelectTrigger id="user-role" className="w-full">
                            <SelectValue placeholder="Elige un rol" />
                        </SelectTrigger>
                        <SelectContent>
                            {roles?.map((role) => (
                                <SelectItem key={role.id} value={String(role.id)}>
                                    {role.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
                <Saved show={update.isSuccess && !changed} />
                <Button disabled={!changed} loading={update.isPending} onClick={() => update.mutate(Number(roleId))}>
                    Cambiar rol
                </Button>
                {isMe && <p className="text-xs text-muted-foreground">No puedes quitarte el permiso de gestionar usuarios.</p>}
            </div>
            <div className="space-y-4">
                <EmailSection user={user} />
                <dl className="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt className="text-xs text-muted-foreground">Estado</dt>
                        <dd className="font-medium text-foreground">
                            {user.deactivated_at ? 'Desactivado' : user.email_verified_at ? 'Activo' : 'Invitación pendiente'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs text-muted-foreground">Último acceso</dt>
                        <dd className="font-medium text-foreground">{user.last_login_at ? formatDateTime(user.last_login_at) : 'Nunca'}</dd>
                    </div>
                    <div>
                        <dt className="text-xs text-muted-foreground">Alta</dt>
                        <dd className="font-medium text-foreground">{formatDateTime(user.created_at)}</dd>
                    </div>
                </dl>
            </div>
        </div>
    );
}

function SecurityTab({ user, isMe }: { user: TenantUser; isMe: boolean }) {
    const reset = useResetUserMfa();
    const [confirming, setConfirming] = useState(false);

    return (
        <div className="max-w-xl space-y-4">
            <div className="flex items-start gap-3 rounded-lg border p-4">
                {user.mfa_enabled ? (
                    <ShieldCheck className="mt-0.5 size-5 text-success" />
                ) : (
                    <ShieldOff className="mt-0.5 size-5 text-muted-foreground" />
                )}
                <div className="flex-1 space-y-1">
                    <p className="flex items-center gap-2 font-medium text-foreground">
                        Verificación en dos pasos
                        {user.mfa_enabled ? <Badge variant="success">Activa</Badge> : <Badge variant="neutral">Sin configurar</Badge>}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {user.mfa_enabled
                            ? 'Entra con su contraseña y el código de su app de autenticación.'
                            : 'La configurará al iniciar sesión (o vence su plazo y se le exigirá).'}
                    </p>
                </div>
            </div>

            {isMe ? (
                <p className="text-sm text-muted-foreground">
                    Tu propia verificación se gestiona en{' '}
                    <Link to="/settings/security" className="font-medium text-primary hover:underline dark:text-brand-200">
                        Mi perfil → Seguridad
                    </Link>
                    .
                </p>
            ) : (
                user.mfa_enabled && (
                    <div className="space-y-2">
                        <p className="text-sm text-muted-foreground">
                            Si perdió su teléfono, sus códigos y su correo de respaldo, restablece su verificación: tendrá que
                            configurarla de nuevo al entrar. Confirma antes su identidad por otro medio.
                        </p>
                        <Button variant="ghosterror" onClick={() => setConfirming(true)}>
                            <ShieldOff />
                            Restablecer verificación en dos pasos
                        </Button>
                    </div>
                )
            )}

            <ConfirmDialog
                open={confirming}
                title="Restablecer verificación en dos pasos"
                description={`${user.name} perderá su app de autenticación, sus códigos y su correo de respaldo.`}
                confirmLabel="Restablecer"
                destructive
                loading={reset.isPending}
                onConfirm={() => reset.mutate(user.id, { onSettled: () => setConfirming(false) })}
                onCancel={() => setConfirming(false)}
            />
        </div>
    );
}

/** Ficha completa de un usuario de la cuenta (Usuarios → Editar). */
export default function UserDetailDialog({
    open,
    userId,
    fallback,
    onClose,
}: {
    open: boolean;
    userId: number;
    fallback: TenantUser;
    onClose: () => void;
}) {
    const { data: users } = useTenantUsers();
    const { data: me } = useAuthUser();
    const user = users?.find((candidate) => candidate.id === userId) ?? fallback;
    const isMe = user.id === me?.id;
    const [tab, setTab] = useState<TabKey>('data');

    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent className="max-h-[92vh] max-w-4xl overflow-y-auto">
                <DialogHeader className="flex-row items-center gap-4 space-y-0 text-left">
                    <UserAvatar name={user.name} photoUrl={user.photo_url} className="size-12" />
                    <div className="min-w-0">
                        <DialogTitle className="truncate">{user.name}</DialogTitle>
                        <DialogDescription className="truncate">
                            {[user.job_title, user.roles.map((role) => role.label).join(', '), user.email].filter(Boolean).join(' · ')}
                        </DialogDescription>
                    </div>
                </DialogHeader>

                <Tabs
                    tabs={[
                        { key: 'data', label: 'Datos personales' },
                        { key: 'access', label: 'Rol y acceso' },
                        { key: 'security', label: 'Seguridad' },
                    ]}
                    active={tab}
                    onChange={setTab}
                />

                {tab === 'data' && <PersonalDataTab user={user} />}
                {tab === 'access' && <AccessTab user={user} isMe={isMe} />}
                {tab === 'security' && <SecurityTab user={user} isMe={isMe} />}
            </DialogContent>
        </Dialog>
    );
}
