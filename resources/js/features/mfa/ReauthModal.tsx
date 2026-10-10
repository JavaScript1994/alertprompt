import { zodResolver } from '@hookform/resolvers/zod';
import { ShieldAlert } from 'lucide-react';
import { useSyncExternalStore } from 'react';
import { useForm } from 'react-hook-form';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { apiErrorMessage } from '@/lib/format';
import { useReauth } from './api';
import CodeInput from './CodeInput';
import { reauthStore, type PendingReauth } from './reauthStore';
import { reauthFormSchema, type ReauthForm } from './schemas';

function ReauthFormBody({ pending }: { pending: PendingReauth }) {
    const reauth = useReauth();
    const {
        register,
        handleSubmit,
        formState: { errors },
    } = useForm<ReauthForm>({ resolver: zodResolver(reauthFormSchema) });

    const submit = handleSubmit((values) =>
        reauth.mutate({ action: pending.action, ...values }, { onSuccess: () => reauthStore.settle(true) }),
    );

    return (
        <form onSubmit={submit} className="space-y-4" noValidate>
            <DialogHeader>
                <DialogTitle className="flex items-center gap-2">
                    <ShieldAlert className="size-5 text-warning" />
                    Confirma que eres tú
                </DialogTitle>
                <DialogDescription>
                    {pending.label}: ingresa tu contraseña y el código de tu app de autenticación. Vale por 15 minutos para
                    esta acción.
                </DialogDescription>
            </DialogHeader>
            <Field label="Contraseña" htmlFor="reauth-password" error={errors.password?.message}>
                <Input id="reauth-password" type="password" autoComplete="current-password" {...register('password')} />
            </Field>
            <Field
                label="Código de la app"
                htmlFor="reauth-code"
                error={errors.code?.message ?? (reauth.isError ? apiErrorMessage(reauth.error, ['code'], 'No se pudo confirmar.') : null)}
            >
                <CodeInput id="reauth-code" {...register('code')} />
            </Field>
            <DialogFooter>
                <Button type="button" variant="outline" onClick={() => reauthStore.settle(false)}>
                    Cancelar
                </Button>
                <Button type="submit" loading={reauth.isPending}>
                    Confirmar
                </Button>
            </DialogFooter>
        </form>
    );
}

/** Se abre sola ante un 403 reauth_required (ver interceptors.ts). */
export default function ReauthModal() {
    const pending = useSyncExternalStore(reauthStore.subscribe, reauthStore.getSnapshot);

    return (
        <Dialog open={pending !== null} onOpenChange={(open) => !open && reauthStore.settle(false)}>
            <DialogContent className="max-w-md">{pending && <ReauthFormBody key={pending.action} pending={pending} />}</DialogContent>
        </Dialog>
    );
}
