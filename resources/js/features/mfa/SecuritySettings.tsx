import { KeyRound, Mail, ShieldCheck, ShieldOff, Smartphone } from 'lucide-react';
import { useState } from 'react';
import ConfirmDialog from '@/components/shared/ConfirmDialog';
import PageHeader from '@/components/shared/PageHeader';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { useAuthUser } from '@/hooks/useAuth';
import { formatDate } from '@/components/shared/MembershipBits';
import { apiErrorMessage } from '@/lib/format';
import { useDisableMfa, useRegenerateRecoveryCodes } from './api';
import EmailBackupSetup from './EmailBackupSetup';
import MfaEnrollment from './MfaEnrollment';
import RecoveryCodes from './RecoveryCodes';

type Panel = 'enroll' | 'email' | 'codes' | null;

/**
 * Seguridad del usuario: estado del MFA, cambio de dispositivo, códigos de
 * recuperación, correo de respaldo y desactivación. Las acciones sensibles
 * abren ReauthModal por el 403 reauth_required del backend.
 */
export default function SecuritySettings() {
    const { data: user, isLoading } = useAuthUser();
    const regenerate = useRegenerateRecoveryCodes();
    const disable = useDisableMfa();
    const [panel, setPanel] = useState<Panel>(null);
    const [confirmDisable, setConfirmDisable] = useState(false);

    if (isLoading || !user) return <Skeleton className="h-96 w-full rounded-xl" />;

    const { mfa } = user;
    const error = regenerate.error ?? disable.error;

    return (
        <div>
            <PageHeader title="Seguridad" description="Verificación en dos pasos de tu usuario." />

            {error && (
                <Alert variant="error" className="mb-6">
                    <AlertTitle>{apiErrorMessage(error, [], 'No se pudo completar la acción.')}</AlertTitle>
                </Alert>
            )}

            {mfa.can_reenroll && (
                <Alert variant="warning" className="mb-6">
                    <Smartphone />
                    <AlertTitle>Entraste con un método de respaldo</AlertTitle>
                    <AlertDescription className="flex flex-wrap items-center justify-between gap-3">
                        <span>Configura tu nuevo teléfono ahora. Tus códigos anteriores dejarán de servir.</span>
                        <Button size="sm" onClick={() => setPanel('enroll')}>
                            Configurar nuevo dispositivo
                        </Button>
                    </AlertDescription>
                </Alert>
            )}

            {mfa.enabled && mfa.should_regenerate_codes && (
                <Alert variant="warning" className="mb-6">
                    <KeyRound />
                    <AlertTitle>Te quedan {mfa.recovery_codes_remaining} códigos de recuperación</AlertTitle>
                    <AlertDescription>Genera códigos nuevos para no quedarte sin respaldo.</AlertDescription>
                </Alert>
            )}

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            App de autenticación
                            {mfa.enabled ? <Badge variant="success">Activa</Badge> : <Badge variant="warning">Sin configurar</Badge>}
                        </CardTitle>
                        <CardDescription>
                            {mfa.enabled
                                ? `Método principal desde el ${formatDate((mfa.confirmed_at ?? '').slice(0, 10))}.`
                                : mfa.grace_ends_at
                                  ? `Debes configurarla antes del ${formatDate(mfa.grace_ends_at.slice(0, 10))}.`
                                  : 'Obligatoria para tu usuario.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-2">
                        {mfa.enabled ? (
                            <>
                                <Button variant="outline" onClick={() => setPanel('enroll')}>
                                    <Smartphone />
                                    Cambiar de dispositivo
                                </Button>
                                <Button variant="ghosterror" onClick={() => setConfirmDisable(true)}>
                                    <ShieldOff />
                                    Desactivar
                                </Button>
                            </>
                        ) : (
                            <Button onClick={() => setPanel('enroll')}>
                                <ShieldCheck />
                                Configurar ahora
                            </Button>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Respaldo si pierdes el teléfono</CardTitle>
                        <CardDescription>Solo se usan desde "¿Perdiste tu dispositivo?" al iniciar sesión.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p className="font-medium text-foreground">Códigos de recuperación</p>
                                <p className="text-muted-foreground">
                                    {mfa.enabled ? `${mfa.recovery_codes_remaining} disponibles` : 'Se generan al activar la app.'}
                                </p>
                            </div>
                            {mfa.enabled && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    loading={regenerate.isPending}
                                    onClick={() => regenerate.mutate(undefined, { onSuccess: () => setPanel('codes') })}
                                >
                                    <KeyRound />
                                    Generar nuevos
                                </Button>
                            )}
                        </div>
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p className="font-medium text-foreground">Correo de respaldo</p>
                                <p className="text-muted-foreground">{mfa.email_backup ?? 'Sin configurar'}</p>
                            </div>
                            <Button variant="outline" size="sm" onClick={() => setPanel('email')}>
                                <Mail />
                                {mfa.email_backup ? 'Cambiar' : 'Configurar'}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Dialog open={panel !== null} onOpenChange={(open) => !open && setPanel(null)}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {panel === 'enroll' && (mfa.enabled ? 'Cambiar de dispositivo' : 'Configurar app de autenticación')}
                            {panel === 'email' && 'Correo de respaldo'}
                            {panel === 'codes' && 'Códigos de recuperación nuevos'}
                        </DialogTitle>
                        <DialogDescription>
                            {panel === 'codes'
                                ? 'Los anteriores ya no sirven.'
                                : panel === 'email'
                                  ? 'Te enviaremos un código para verificarlo.'
                                  : 'Tu app actual sigue funcionando hasta que confirmes la nueva.'}
                        </DialogDescription>
                    </DialogHeader>
                    {panel === 'enroll' && <MfaEnrollment user={user} onFinished={() => setPanel(null)} />}
                    {panel === 'email' && <EmailBackupSetup onDone={() => setPanel(null)} />}
                    {panel === 'codes' && regenerate.data && (
                        <>
                            <RecoveryCodes codes={regenerate.data} email={user.email} />
                            <DialogFooter>
                                <Button onClick={() => setPanel(null)}>Listo, los guardé</Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={confirmDisable}
                title="¿Desactivar la verificación en dos pasos?"
                description={
                    mfa.required_now || user.permissions.includes('users.manage')
                        ? 'Tu usuario la tiene obligatoria: tendrás que configurarla de nuevo para seguir usando el panel. Avisaremos a los administradores.'
                        : 'Tu cuenta quedará protegida solo con la contraseña. Avisaremos a los administradores.'
                }
                confirmLabel="Desactivar"
                destructive
                loading={disable.isPending}
                onConfirm={() => disable.mutate(undefined, { onSettled: () => setConfirmDisable(false) })}
                onCancel={() => setConfirmDisable(false)}
            />
        </div>
    );
}
