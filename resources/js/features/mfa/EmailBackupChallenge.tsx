import { zodResolver } from '@hookform/resolvers/zod';
import { MailCheck } from 'lucide-react';
import { useForm } from 'react-hook-form';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { apiErrorMessage } from '@/lib/format';
import { useEmailBackupChallenge, useEmailBackupLogin } from './api';
import CodeInput from './CodeInput';
import { codeFormSchema, type CodeForm } from './schemas';

/** Perdí mi dispositivo → código al correo de respaldo verificado. */
export default function EmailBackupChallenge({ challengeToken, onBack }: { challengeToken: string; onBack: () => void }) {
    const send = useEmailBackupChallenge();
    const verify = useEmailBackupLogin();
    const form = useForm<CodeForm>({ resolver: zodResolver(codeFormSchema) });

    const submit = form.handleSubmit(({ code }) => verify.mutate({ challenge_token: challengeToken, code }));

    if (!send.isSuccess) {
        return (
            <div className="space-y-4">
                <p className="text-sm text-muted-foreground">
                    Te enviaremos un código de un solo uso a tu correo de respaldo. Avisaremos a los administradores de tu
                    cuenta de este acceso.
                </p>
                {send.isError && (
                    <Alert variant="error">
                        <AlertTitle>{apiErrorMessage(send.error, [], 'No se pudo enviar el código.')}</AlertTitle>
                    </Alert>
                )}
                <Button className="w-full" loading={send.isPending} onClick={() => send.mutate(challengeToken)}>
                    Enviar código a mi correo de respaldo
                </Button>
                <Button variant="ghost" className="w-full" onClick={onBack}>
                    Volver
                </Button>
            </div>
        );
    }

    return (
        <form onSubmit={submit} className="space-y-4" noValidate>
            <Alert variant="info">
                <MailCheck />
                <AlertTitle>Revisa tu correo de respaldo ({send.data.email}). El código vence en 10 minutos.</AlertTitle>
            </Alert>
            <Field
                label="Código del correo"
                htmlFor="email-backup-code"
                error={form.formState.errors.code?.message ?? (verify.isError ? apiErrorMessage(verify.error, ['code'], 'No se pudo verificar.') : null)}
            >
                <CodeInput id="email-backup-code" {...form.register('code')} />
            </Field>
            <Button type="submit" className="w-full" loading={verify.isPending}>
                Entrar
            </Button>
            <div className="flex justify-between text-sm">
                <button type="button" className="text-muted-foreground hover:text-foreground" onClick={onBack}>
                    Volver
                </button>
                <button
                    type="button"
                    className="font-medium text-tenant-accent hover:underline dark:text-prompt-400"
                    disabled={send.isPending}
                    onClick={() => send.mutate(challengeToken)}
                >
                    Enviar otro código
                </button>
            </div>
        </form>
    );
}
