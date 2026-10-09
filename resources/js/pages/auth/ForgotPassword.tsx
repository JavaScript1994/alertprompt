import { zodResolver } from '@hookform/resolvers/zod';
import { ArrowLeft, CheckCircle2, Mail } from 'lucide-react';
import { useForm } from 'react-hook-form';
import { Link } from 'react-router-dom';
import { z } from 'zod';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { useForgotPassword } from '@/hooks/useAuth';
import { apiErrorMessage } from '@/lib/format';

const schema = z.object({
    email: z.string().min(1, 'Ingresa tu correo electrónico').email('Correo electrónico inválido'),
});

type ForgotForm = z.infer<typeof schema>;

export default function ForgotPassword() {
    const forgot = useForgotPassword();
    const {
        register,
        handleSubmit,
        formState: { errors },
    } = useForm<ForgotForm>({ resolver: zodResolver(schema) });

    const onSubmit = handleSubmit(({ email }) => forgot.mutate(email));

    return (
        <div>
            <div className="text-center">
                <h2 className="text-[2rem] font-bold tracking-tight text-brand-700 shorter:text-[1.75rem] dark:text-white">
                    Recupera tu acceso
                </h2>
                <p className="mt-3 text-muted-foreground">Te enviaremos un enlace para crear una nueva contraseña.</p>
            </div>

            {forgot.isSuccess ? (
                <Alert variant="success" className="mt-10">
                    <CheckCircle2 />
                    <AlertTitle>{forgot.data}</AlertTitle>
                </Alert>
            ) : (
                <form onSubmit={onSubmit} className="mt-10 space-y-6" noValidate>
                    <Field label="Correo electrónico" htmlFor="email" error={errors.email?.message} className="space-y-2.5">
                        <div className="relative">
                            <Mail className="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                id="email"
                                type="email"
                                autoComplete="email"
                                placeholder="tu@empresa.com"
                                className="h-14 rounded-lg bg-card pl-12 text-base shorter:h-12"
                                {...register('email')}
                            />
                        </div>
                    </Field>

                    {forgot.isError && (
                        <Alert variant="error">
                            <AlertTitle>{apiErrorMessage(forgot.error, ['email'], 'No pudimos enviar el enlace. Intenta de nuevo.')}</AlertTitle>
                        </Alert>
                    )}

                    <Button
                        type="submit"
                        loading={forgot.isPending}
                        className="h-14 w-full rounded-lg bg-tenant-accent text-base hover:bg-tenant-accent/90 shorter:h-12"
                    >
                        Enviar enlace
                    </Button>
                </form>
            )}

            <p className="mt-8 text-center text-sm">
                <Link to="/login" className="inline-flex items-center gap-1.5 font-semibold text-tenant-accent hover:underline dark:text-prompt-400">
                    <ArrowLeft className="size-4" />
                    Volver a iniciar sesión
                </Link>
            </p>
        </div>
    );
}
