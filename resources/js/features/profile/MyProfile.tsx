import { zodResolver } from '@hookform/resolvers/zod';
import { CheckCircle2, Mail, ShieldCheck, ShieldAlert } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import PageHeader from '@/components/shared/PageHeader';
import Tabs from '@/components/shared/Tabs';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import SecuritySettings from '@/features/mfa/SecuritySettings';
import { useAuthUser } from '@/hooks/useAuth';
import { apiErrorMessage, formatDateTime } from '@/lib/format';
import { applyServerErrors } from '@/lib/forms';
import type { User } from '@/types';
import { useDeleteProfilePhoto, useUpdateProfile, useUploadProfilePhoto } from './api';
import PersonalDataFields, { PersonalDataNotice } from './PersonalDataFields';
import PhotoField from './PhotoField';
import { PERSONAL_KEYS, personalDataSchema, personalDefaults, type PersonalDataInput, type PersonalDataOutput } from './schemas';
import UserAvatar from './UserAvatar';

type TabKey = 'data' | 'security';

function PersonalDataTab({ user }: { user: User }) {
    const update = useUpdateProfile();
    const upload = useUploadProfilePhoto();
    const removePhoto = useDeleteProfilePhoto();
    const [saved, setSaved] = useState(false);
    const [generalError, setGeneralError] = useState<string | null>(null);

    const {
        register,
        handleSubmit,
        setError,
        formState: { errors, isDirty },
    } = useForm<PersonalDataInput, unknown, PersonalDataOutput>({
        resolver: zodResolver(personalDataSchema),
        // `values`: el formulario se sincroniza con el usuario guardado.
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
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
            <Card>
                <CardHeader>
                    <CardTitle>Foto</CardTitle>
                    <CardDescription>Se muestra en tu avatar y en la ficha que ve tu administrador.</CardDescription>
                </CardHeader>
                <CardContent>
                    <PhotoField
                        layout="stacked"
                        size="lg"
                        name={user.name}
                        photoUrl={user.photo_url}
                        busy={upload.isPending || removePhoto.isPending}
                        onUpload={(file) => upload.mutate(file)}
                        onRemove={() => removePhoto.mutate()}
                        error={photoError ? apiErrorMessage(photoError, ['photo'], 'No se pudo guardar la foto.') : null}
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Datos personales</CardTitle>
                    <PersonalDataNotice />
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit} className="space-y-5" noValidate>
                        {saved && !isDirty && (
                            <Alert variant="success">
                                <CheckCircle2 />
                                <AlertTitle>Datos guardados.</AlertTitle>
                            </Alert>
                        )}
                        {generalError && (
                            <Alert variant="error">
                                <AlertTitle>{generalError}</AlertTitle>
                            </Alert>
                        )}
                        <PersonalDataFields
                            idPrefix="profile"
                            register={(key) => register(key)}
                            errors={Object.fromEntries(PERSONAL_KEYS.map((key) => [key, errors[key]?.message]))}
                        />
                        <div className="flex items-center gap-2 rounded-lg border bg-muted/40 px-3 py-2.5 text-sm">
                            <Mail className="size-4 text-muted-foreground" />
                            <span className="font-medium text-foreground">{user.email}</span>
                            <span className="text-muted-foreground">· Para cambiarlo, pídelo al administrador de tu cuenta.</span>
                        </div>
                        <div className="flex justify-end">
                            <Button type="submit" loading={update.isPending} disabled={!isDirty}>
                                Guardar cambios
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}

/** Mi perfil: datos personales y seguridad del usuario con sesión (todos los roles). */
export default function MyProfile({ initialTab = 'data' }: { initialTab?: TabKey }) {
    const { data: user, isLoading } = useAuthUser();
    const [tab, setTab] = useState<TabKey>(initialTab);

    if (isLoading || !user) return <Skeleton className="h-96 w-full rounded-xl" />;

    return (
        <div>
            <PageHeader title="Mi perfil" description="Tus datos y la seguridad de tu acceso." />

            <Card className="mb-6 gap-0 p-0">
                <CardContent className="flex flex-wrap items-center gap-4 px-6 py-4">
                    <UserAvatar name={user.name} photoUrl={user.photo_url} className="size-14" fallbackClassName="text-base" />
                    <div className="min-w-[12rem] flex-1">
                        <p className="truncate text-lg font-semibold text-foreground">{user.name}</p>
                        <p className="truncate text-sm text-muted-foreground">
                            {[user.job_title, user.roles.map((role) => role.label).join(', '), user.tenant.name].filter(Boolean).join(' · ')}
                        </p>
                    </div>
                    <div className="flex w-full flex-col items-start gap-1 text-xs text-muted-foreground sm:w-auto sm:items-end">
                        {user.mfa.enabled ? (
                            <Badge variant="success">
                                <ShieldCheck />
                                Verificación en dos pasos activa
                            </Badge>
                        ) : (
                            <Badge variant="warning">
                                <ShieldAlert />
                                Sin verificación en dos pasos
                            </Badge>
                        )}
                        {user.last_login_at && <span>Último acceso: {formatDateTime(user.last_login_at)}</span>}
                    </div>
                </CardContent>
            </Card>

            <Tabs
                tabs={[
                    { key: 'data', label: 'Datos personales' },
                    { key: 'security', label: 'Seguridad' },
                ]}
                active={tab}
                onChange={setTab}
            />

            {tab === 'data' && <PersonalDataTab user={user} />}
            {tab === 'security' && <SecuritySettings />}
        </div>
    );
}
