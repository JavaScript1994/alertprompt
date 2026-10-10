import { zodResolver } from '@hookform/resolvers/zod';
import { ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { apiErrorMessage } from '@/lib/format';
import { mfaErrorCode, useRecoveryLogin, useVerifyMfa } from './api';
import CodeInput from './CodeInput';
import EmailBackupChallenge from './EmailBackupChallenge';
import { codeFormSchema, recoveryCodeFormSchema, type CodeForm, type RecoveryCodeForm } from './schemas';
import type { LoginChallenge } from './types';

type Mode = 'totp' | 'lost' | 'recovery' | 'email';

/**
 * Segundo paso del login. La app de autenticación es el método principal;
 * los respaldos solo aparecen tras "¿Perdiste tu dispositivo?". No hay
 * elección de método.
 */
export default function MfaChallenge({ challenge, onRestart }: { challenge: LoginChallenge; onRestart: () => void }) {
    const [mode, setMode] = useState<Mode>('totp');
    const verify = useVerifyMfa();
    const recovery = useRecoveryLogin();

    const totpForm = useForm<CodeForm>({ resolver: zodResolver(codeFormSchema) });
    const recoveryForm = useForm<RecoveryCodeForm>({ resolver: zodResolver(recoveryCodeFormSchema) });

    const token = challenge.challenge_token;
    const expired = [verify.error, recovery.error].some((error) => mfaErrorCode(error) === 'challenge_invalid');

    if (expired) {
        return (
            <div className="space-y-4">
                <Alert variant="warning">
                    <AlertTitle>La verificación venció. Vuelve a ingresar tu contraseña.</AlertTitle>
                </Alert>
                <Button className="w-full" onClick={onRestart}>
                    Volver al inicio de sesión
                </Button>
            </div>
        );
    }

    if (mode === 'email') {
        return <EmailBackupChallenge challengeToken={token} onBack={() => setMode('lost')} />;
    }

    if (mode === 'lost') {
        return (
            <div className="space-y-3">
                <p className="text-sm text-muted-foreground">
                    Tu app de autenticación es el método principal. Si no la tienes, usa una opción de respaldo. Avisaremos a
                    los administradores de tu cuenta.
                </p>
                <Button variant="outline" className="w-full" onClick={() => setMode('recovery')}>
                    Usar un código de recuperación
                </Button>
                {challenge.email_backup_available && (
                    <Button variant="outline" className="w-full" onClick={() => setMode('email')}>
                        Usar respaldo por email
                    </Button>
                )}
                {!challenge.email_backup_available && (
                    <p className="text-xs text-muted-foreground">
                        Si tampoco tienes tus códigos, pide al administrador de tu cuenta que restablezca tu verificación en dos
                        pasos.
                    </p>
                )}
                <Button variant="ghost" className="w-full" onClick={() => setMode('totp')}>
                    Volver
                </Button>
            </div>
        );
    }

    if (mode === 'recovery') {
        const submit = recoveryForm.handleSubmit((values) => recovery.mutate({ challenge_token: token, ...values }));

        return (
            <form onSubmit={submit} className="space-y-4" noValidate>
                <Field
                    label="Código de recuperación"
                    htmlFor="recovery-code"
                    hint="Uno de los códigos XXXXX-XXXXX que guardaste. Cada uno sirve una vez."
                    error={
                        recoveryForm.formState.errors.recovery_code?.message ??
                        (recovery.isError ? apiErrorMessage(recovery.error, ['recovery_code'], 'No se pudo verificar.') : null)
                    }
                >
                    <Input id="recovery-code" autoComplete="off" className="h-12 font-mono uppercase tracking-wider" {...recoveryForm.register('recovery_code')} />
                </Field>
                <Button type="submit" className="w-full" loading={recovery.isPending}>
                    Entrar
                </Button>
                <Button type="button" variant="ghost" className="w-full" onClick={() => setMode('lost')}>
                    Volver
                </Button>
            </form>
        );
    }

    const submit = totpForm.handleSubmit(({ code }) => verify.mutate({ challenge_token: token, code }));

    return (
        <form onSubmit={submit} className="space-y-5" noValidate>
            <div className="flex items-start gap-3 rounded-lg border bg-muted/40 p-4 text-sm">
                <ShieldCheck className="mt-0.5 size-5 shrink-0 text-primary" />
                <p>Abre tu app de autenticación e ingresa el código de 6 dígitos.</p>
            </div>
            <Field
                label="Código de verificación"
                htmlFor="mfa-code"
                error={totpForm.formState.errors.code?.message ?? (verify.isError ? apiErrorMessage(verify.error, ['code'], 'No se pudo verificar.') : null)}
            >
                <CodeInput id="mfa-code" {...totpForm.register('code')} />
            </Field>
            <Button type="submit" className="h-12 w-full bg-tenant-accent hover:bg-tenant-accent/90" loading={verify.isPending}>
                Verificar
            </Button>
            <div className="flex justify-between text-sm">
                <button type="button" className="text-muted-foreground hover:text-foreground" onClick={onRestart}>
                    Cambiar de usuario
                </button>
                <button
                    type="button"
                    className="font-medium text-tenant-accent hover:underline dark:text-prompt-400"
                    onClick={() => setMode('lost')}
                >
                    ¿Perdiste tu dispositivo?
                </button>
            </div>
        </form>
    );
}
