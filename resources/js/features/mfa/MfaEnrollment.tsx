import { zodResolver } from '@hookform/resolvers/zod';
import { KeyRound, ShieldCheck, Smartphone } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field } from '@/components/ui/field';
import { Label } from '@/components/ui/label';
import { apiErrorMessage } from '@/lib/format';
import type { User } from '@/types';
import { useApplyUser, useConfirmMfa, useStartMfaSetup } from './api';
import CodeInput from './CodeInput';
import EmailBackupSetup from './EmailBackupSetup';
import RecoveryCodes from './RecoveryCodes';
import { codeFormSchema, type CodeForm } from './schemas';

type Step = 'intro' | 'scan' | 'codes' | 'email' | 'done';

/**
 * Enrolamiento de la app de autenticación (TOTP): QR → primer código →
 * códigos de recuperación (una vez) → correo de respaldo (opcional).
 * También sirve para cambiar de dispositivo.
 */
export default function MfaEnrollment({ user, onFinished }: { user: User; onFinished: () => void }) {
    const setup = useStartMfaSetup();
    const confirm = useConfirmMfa();
    const applyUser = useApplyUser();
    const [step, setStep] = useState<Step>('intro');
    const [savedCodes, setSavedCodes] = useState(false);
    const [confirmed, setConfirmed] = useState<{ codes: string[]; user: User } | null>(null);

    const form = useForm<CodeForm>({ resolver: zodResolver(codeFormSchema) });

    const begin = () => setup.mutate(undefined, { onSuccess: () => setStep('scan') });
    const submitCode = form.handleSubmit(({ code }) =>
        confirm.mutate(code, {
            onSuccess: (result) => {
                setConfirmed({ codes: result.recovery_codes, user: result.user });
                setStep('codes');
            },
        }),
    );

    const finish = () => {
        if (confirmed) applyUser(confirmed.user);
        onFinished();
    };

    if (step === 'intro') {
        return (
            <div className="space-y-5">
                <div className="flex items-start gap-3 rounded-lg border bg-muted/40 p-4 text-sm">
                    <Smartphone className="mt-0.5 size-5 shrink-0 text-primary" />
                    <p>
                        Necesitas una app de autenticación en tu teléfono, por ejemplo Google Authenticator, Microsoft
                        Authenticator o 1Password. Cada vez que entres te pediremos el código de 6 dígitos que muestra.
                    </p>
                </div>
                {setup.isError && (
                    <Alert variant="error">
                        <AlertTitle>{apiErrorMessage(setup.error, [], 'No se pudo iniciar la configuración.')}</AlertTitle>
                    </Alert>
                )}
                <Button className="w-full" onClick={begin} loading={setup.isPending}>
                    <ShieldCheck />
                    Configurar app de autenticación
                </Button>
            </div>
        );
    }

    if (step === 'scan' && setup.data) {
        return (
            <form onSubmit={submitCode} className="space-y-5" noValidate>
                <ol className="list-decimal space-y-1 pl-5 text-sm text-muted-foreground">
                    <li>Abre tu app de autenticación y agrega una cuenta nueva.</li>
                    <li>Escanea este código QR.</li>
                    <li>Escribe el código de 6 dígitos que aparece.</li>
                </ol>
                <img
                    src={`data:image/svg+xml;charset=utf-8,${encodeURIComponent(setup.data.qr_svg)}`}
                    alt="Código QR para la app de autenticación"
                    className="mx-auto size-[220px] rounded-lg border bg-white p-2"
                />
                <details className="text-sm">
                    <summary className="cursor-pointer text-muted-foreground hover:text-foreground">¿No puedes escanear? Ingresa la clave</summary>
                    <p className="mt-2 break-all rounded-md bg-muted px-3 py-2 font-mono text-xs tracking-wider select-all">
                        {setup.data.secret}
                    </p>
                </details>
                <Field
                    label="Código de la app"
                    htmlFor="enroll-code"
                    error={form.formState.errors.code?.message ?? (confirm.isError ? apiErrorMessage(confirm.error, ['code'], 'No se pudo confirmar.') : null)}
                >
                    <CodeInput id="enroll-code" {...form.register('code')} />
                </Field>
                <Button type="submit" className="w-full" loading={confirm.isPending}>
                    Activar
                </Button>
            </form>
        );
    }

    if (step === 'codes' && confirmed) {
        return (
            <div className="space-y-5">
                <Alert variant="success">
                    <KeyRound />
                    <AlertTitle>Verificación en dos pasos activada</AlertTitle>
                    <AlertDescription>Guarda estos códigos de recuperación antes de continuar.</AlertDescription>
                </Alert>
                <RecoveryCodes codes={confirmed.codes} email={user.email} />
                <div className="flex items-center gap-2.5">
                    <Checkbox id="saved-codes" checked={savedCodes} onCheckedChange={(checked) => setSavedCodes(checked === true)} />
                    <Label htmlFor="saved-codes" className="cursor-pointer font-normal">
                        Guardé mis códigos en un lugar seguro
                    </Label>
                </div>
                <Button
                    className="w-full"
                    disabled={!savedCodes}
                    onClick={() => (user.mfa.email_backup ? finish() : setStep('email'))}
                >
                    Continuar
                </Button>
            </div>
        );
    }

    if (step === 'email') {
        return (
            <div className="space-y-4">
                <div>
                    <p className="font-medium text-foreground">Correo de respaldo (recomendado)</p>
                    <p className="text-sm text-muted-foreground">
                        Si pierdes el teléfono y los códigos, podrás entrar con un código enviado a este correo. Tu app de
                        autenticación sigue siendo el método principal.
                    </p>
                </div>
                <EmailBackupSetup onDone={finish} onSkip={finish} />
            </div>
        );
    }

    return null;
}
