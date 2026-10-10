import { zodResolver } from '@hookform/resolvers/zod';
import { Check, KeyRound, ShieldCheck, Smartphone } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field } from '@/components/ui/field';
import { Label } from '@/components/ui/label';
import { apiErrorMessage } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { User } from '@/types';
import { useApplyUser, useConfirmMfa, useStartMfaSetup } from './api';
import CodeInput from './CodeInput';
import EmailBackupSetup from './EmailBackupSetup';
import RecoveryCodes from './RecoveryCodes';
import { codeFormSchema, type CodeForm } from './schemas';

type Step = 'intro' | 'scan' | 'codes' | 'email';

/*
 * Diseño: el componente es un @container. Con espacio (página de
 * enrolamiento) cada paso va en dos columnas: lo que se mira a la izquierda
 * (QR, códigos) y lo que se hace a la derecha (instrucciones, campo, botón).
 * Angosto (diálogo de Seguridad, móvil) se apila en el orden de lectura:
 * instrucciones → QR → código.
 */
const TWO_COLUMNS = 'grid gap-x-8 gap-y-5 @lg:grid-cols-[minmax(0,14rem)_minmax(0,1fr)]';
/** Lo que se mira: a la izquierda ocupando las dos filas. */
const LEFT = '@lg:col-start-1 @lg:row-span-2 @lg:row-start-1';
const RIGHT_TOP = '@lg:col-start-2 @lg:row-start-1';
const RIGHT_BOTTOM = '@lg:col-start-2 @lg:row-start-2 @lg:self-end';

function Stepper({ current, withBackup }: { current: Step; withBackup: boolean }) {
    const steps = [
        { key: 'scan', label: 'App' },
        { key: 'codes', label: 'Códigos' },
        ...(withBackup ? [{ key: 'email', label: 'Respaldo' }] : []),
    ];
    const index = current === 'intro' ? 0 : steps.findIndex((step) => step.key === current);

    return (
        <ol className="mb-6 flex items-center gap-2 short:mb-4" aria-label="Pasos">
            {steps.map((step, i) => {
                const done = i < index;
                const active = i === index && current !== 'intro';

                return (
                    <li key={step.key} className="flex flex-1 items-center gap-2 last:flex-none" aria-current={active ? 'step' : undefined}>
                        <span
                            className={cn(
                                'flex size-7 shrink-0 items-center justify-center rounded-full border text-xs font-semibold',
                                done && 'border-primary bg-primary text-primary-foreground',
                                active && 'border-primary text-primary dark:border-brand-200 dark:text-brand-200',
                                !done && !active && 'text-muted-foreground',
                            )}
                        >
                            {done ? <Check className="size-3.5" /> : i + 1}
                        </span>
                        <span className={cn('text-sm', active ? 'font-medium text-foreground' : 'text-muted-foreground')}>{step.label}</span>
                        {i < steps.length - 1 && <span className={cn('h-px flex-1', done ? 'bg-primary' : 'bg-border')} />}
                    </li>
                );
            })}
        </ol>
    );
}

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
    const withBackup = user.mfa.email_backup === null;

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

    return (
        <div className="@container">
            <Stepper current={step} withBackup={withBackup} />

            {step === 'intro' && (
                <div className="mx-auto max-w-md space-y-5">
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
            )}

            {step === 'scan' && setup.data && (
                <form onSubmit={submitCode} className={TWO_COLUMNS} noValidate>
                    <ol className={cn('list-decimal space-y-1.5 pl-5 text-sm text-muted-foreground', RIGHT_TOP)}>
                        <li>Abre tu app de autenticación y agrega una cuenta nueva.</li>
                        <li>Escanea el código QR.</li>
                        <li>Escribe el código de 6 dígitos que aparece.</li>
                    </ol>

                    <div className={cn('space-y-3', LEFT)}>
                        <img
                            src={`data:image/svg+xml;charset=utf-8,${encodeURIComponent(setup.data.qr_svg)}`}
                            alt="Código QR para la app de autenticación"
                            className="mx-auto aspect-square w-full max-w-[14rem] rounded-lg border bg-white p-2"
                        />
                        <details className="text-sm">
                            <summary className="cursor-pointer text-center text-muted-foreground hover:text-foreground">
                                ¿No puedes escanear?
                            </summary>
                            <p className="mt-2 text-xs text-muted-foreground">Ingresa esta clave en la app:</p>
                            <p className="mt-1 break-all rounded-md bg-muted px-3 py-2 font-mono text-xs tracking-wider select-all">
                                {setup.data.secret}
                            </p>
                        </details>
                    </div>

                    <div className={cn('space-y-4', RIGHT_BOTTOM)}>
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
                    </div>
                </form>
            )}

            {step === 'codes' && confirmed && (
                <div className={TWO_COLUMNS}>
                    <Alert variant="success" className={RIGHT_TOP}>
                        <KeyRound />
                        <AlertTitle>Verificación en dos pasos activada</AlertTitle>
                        <AlertDescription>
                            Guarda estos códigos de recuperación: son tu forma de entrar si pierdes el teléfono.
                        </AlertDescription>
                    </Alert>

                    <div className={LEFT}>
                        <RecoveryCodes codes={confirmed.codes} email={user.email} />
                    </div>

                    <div className={cn('space-y-4', RIGHT_BOTTOM)}>
                        <div className="flex items-start gap-2.5">
                            <Checkbox id="saved-codes" checked={savedCodes} onCheckedChange={(checked) => setSavedCodes(checked === true)} />
                            <Label htmlFor="saved-codes" className="cursor-pointer leading-snug font-normal">
                                Guardé mis códigos en un lugar seguro
                            </Label>
                        </div>
                        <Button className="w-full" disabled={!savedCodes} onClick={() => (withBackup ? setStep('email') : finish())}>
                            Continuar
                        </Button>
                    </div>
                </div>
            )}

            {step === 'email' && (
                <div className="grid gap-x-8 gap-y-5 @lg:grid-cols-2">
                    <div>
                        <p className="font-medium text-foreground">Correo de respaldo (recomendado)</p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Si pierdes el teléfono y los códigos, podrás entrar con un código enviado a este correo. Tu app de
                            autenticación sigue siendo el método principal.
                        </p>
                    </div>
                    <EmailBackupSetup onDone={finish} onSkip={finish} />
                </div>
            )}
        </div>
    );
}
