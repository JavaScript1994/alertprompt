import { zodResolver } from '@hookform/resolvers/zod';
import { ArrowRight, CheckCircle2, Lock } from 'lucide-react';
import { useForm } from 'react-hook-form';
import { Link, useSearchParams } from 'react-router-dom';
import { z } from 'zod';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { useResetPassword } from '@/hooks/useAuth';
import { apiErrorMessage } from '@/lib/format';

const schema = z
    .object({
        password: z
            .string()
            .min(8, 'Mínimo 8 caracteres')
            .regex(/[A-Za-z]/, 'Incluye al menos una letra')
            .regex(/\d/, 'Incluye al menos un número'),
        password_confirmation: z.string(),
    })
    .refine((values) => values.password === values.password_confirmation, {
        message: 'Las contraseñas no coinciden',
        path: ['password_confirmation'],
    });

type ResetForm = z.infer<typeof schema>;

const inputClasses = 'h-14 rounded-lg bg-card pl-12 text-base shorter:h-12';
const iconClasses = 'pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-muted-foreground';

/** Sirve para "olvidé mi contraseña" y para aceptar una invitación (?invite=1). */
export default function ResetPassword() {
    const [params] = useSearchParams();
    const token = params.get('token') ?? '';
    const email = params.get('email') ?? '';
    const isInvite = params.get('invite') === '1';
    const reset = useResetPassword();

    const {
        register,
        handleSubmit,
        formState: { errors },
    } = useForm<ResetForm>({ resolver: zodResolver(schema) });

    const onSubmit = handleSubmit((values) => reset.mutate({ ...values, token, email, invite: isInvite }));

    if (!token || !email) {
        return (
            <Alert variant="error">
                <AlertTitle>El enlace está incompleto. Ábrelo de nuevo desde tu correo.</AlertTitle>
            </Alert>
        );
    }

    return (
        <div>
            <div className="text-center">
                <h2 className="text-[2rem] font-bold tracking-tight text-brand-700 shorter:text-[1.75rem] dark:text-white">
                    {isInvite ? 'Crea tu contraseña' : 'Nueva contraseña'}
                </h2>
                <p className="mt-3 text-muted-foreground">
                    Para <span className="font-medium text-foreground">{email}</span>
                </p>
            </div>

            {reset.isSuccess ? (
                <div className="mt-10 space-y-6">
                    <Alert variant="success">
                        <CheckCircle2 />
                        <AlertTitle>{reset.data}</AlertTitle>
                    </Alert>
                    <Button asChild className="h-14 w-full rounded-lg bg-tenant-accent text-base hover:bg-tenant-accent/90">
                        <Link to="/login">
                            Iniciar sesión
                            <ArrowRight className="size-5" />
                        </Link>
                    </Button>
                </div>
            ) : (
                <form onSubmit={onSubmit} className="mt-10 space-y-6" noValidate>
                    <Field label="Contraseña" htmlFor="password" error={errors.password?.message} hint="Mínimo 8 caracteres, con letras y números." className="space-y-2.5">
                        <div className="relative">
                            <Lock className={iconClasses} />
                            <Input id="password" type="password" autoComplete="new-password" className={inputClasses} {...register('password')} />
                        </div>
                    </Field>
                    <Field label="Repite la contraseña" htmlFor="password_confirmation" error={errors.password_confirmation?.message} className="space-y-2.5">
                        <div className="relative">
                            <Lock className={iconClasses} />
                            <Input
                                id="password_confirmation"
                                type="password"
                                autoComplete="new-password"
                                className={inputClasses}
                                {...register('password_confirmation')}
                            />
                        </div>
                    </Field>

                    {reset.isError && (
                        <Alert variant="error">
                            <AlertTitle>
                                {apiErrorMessage(reset.error, ['email', 'password', 'token'], 'No pudimos guardar tu contraseña.')}
                            </AlertTitle>
                        </Alert>
                    )}

                    <Button
                        type="submit"
                        loading={reset.isPending}
                        className="h-14 w-full rounded-lg bg-tenant-accent text-base hover:bg-tenant-accent/90 shorter:h-12"
                    >
                        Guardar contraseña
                    </Button>
                </form>
            )}
        </div>
    );
}
