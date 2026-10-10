import { zodResolver } from '@hookform/resolvers/zod';
import { MailCheck } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { apiErrorMessage } from '@/lib/format';
import { useConfirmEmailBackup, useStartEmailBackupSetup } from './api';
import CodeInput from './CodeInput';
import { codeFormSchema, emailBackupFormSchema, type CodeForm, type EmailBackupForm } from './schemas';

/**
 * Configura el correo de respaldo: se verifica con un código antes de quedar
 * activo. Solo sirve para entrar si pierdes tu app de autenticación.
 */
export default function EmailBackupSetup({ onDone, onSkip }: { onDone: () => void; onSkip?: () => void }) {
    const start = useStartEmailBackupSetup();
    const confirm = useConfirmEmailBackup();
    const [sentTo, setSentTo] = useState<string | null>(null);

    const emailForm = useForm<EmailBackupForm>({ resolver: zodResolver(emailBackupFormSchema) });
    const codeForm = useForm<CodeForm>({ resolver: zodResolver(codeFormSchema) });

    const sendCode = emailForm.handleSubmit(({ email }) =>
        start.mutate(email, {
            onSuccess: () => {
                setSentTo(email);
                codeForm.reset();
            },
        }),
    );
    const verify = codeForm.handleSubmit(({ code }) => confirm.mutate(code, { onSuccess: onDone }));

    if (sentTo === null) {
        return (
            <form onSubmit={sendCode} className="space-y-4" noValidate>
                <Field
                    label="Correo de respaldo"
                    htmlFor="backup-email"
                    error={emailForm.formState.errors.email?.message ?? (start.isError ? apiErrorMessage(start.error, ['email'], 'No se pudo enviar el código.') : null)}
                    hint="Un correo distinto al de tu usuario, al que tengas acceso si pierdes el teléfono."
                >
                    <Input id="backup-email" type="email" autoComplete="email" {...emailForm.register('email')} />
                </Field>
                <div className="flex flex-wrap gap-2">
                    <Button type="submit" loading={start.isPending}>
                        Enviar código
                    </Button>
                    {onSkip && (
                        <Button type="button" variant="ghost" onClick={onSkip}>
                            Ahora no
                        </Button>
                    )}
                </div>
            </form>
        );
    }

    return (
        <form onSubmit={verify} className="space-y-4" noValidate>
            <Alert variant="info">
                <MailCheck />
                <AlertTitle>Te enviamos un código de 6 dígitos a {sentTo}. Vence en 10 minutos.</AlertTitle>
            </Alert>
            <Field
                label="Código"
                htmlFor="backup-code"
                error={codeForm.formState.errors.code?.message ?? (confirm.isError ? apiErrorMessage(confirm.error, ['code'], 'No se pudo verificar.') : null)}
            >
                <CodeInput id="backup-code" {...codeForm.register('code')} />
            </Field>
            <div className="flex flex-wrap gap-2">
                <Button type="submit" loading={confirm.isPending}>
                    Verificar correo
                </Button>
                <Button type="button" variant="ghost" onClick={() => setSentTo(null)}>
                    Usar otro correo
                </Button>
            </div>
        </form>
    );
}
