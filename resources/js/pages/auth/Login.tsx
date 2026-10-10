import { zodResolver } from '@hookform/resolvers/zod';
import { AxiosError } from 'axios';
import { AlertCircle, ArrowRight, ArrowUpRight, Eye, EyeOff, Lock, Mail } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link, Navigate } from 'react-router-dom';
import { z } from 'zod';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import MfaChallenge from '@/features/mfa/MfaChallenge';
import type { LoginChallenge } from '@/features/mfa/types';
import { useAuthUser, useLogin } from '@/hooks/useAuth';
import ComingSoon from './partials/ComingSoon';

const loginSchema = z.object({
    email: z.string().min(1, 'Ingresa tu correo electrónico').email('Correo electrónico inválido'),
    password: z.string().min(1, 'Ingresa tu contraseña'),
});

type LoginForm = z.infer<typeof loginSchema>;

const inputClasses = 'h-14 rounded-lg bg-card pl-12 text-base shorter:h-12';
const iconClasses = 'pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-muted-foreground';

export default function Login() {
    const { data: user, isLoading: isLoadingUser } = useAuthUser();
    const login = useLogin();
    const [showPassword, setShowPassword] = useState(false);
    const [challenge, setChallenge] = useState<LoginChallenge | null>(null);

    const {
        register,
        handleSubmit,
        formState: { errors },
    } = useForm<LoginForm>({
        resolver: zodResolver(loginSchema),
    });

    // Una sesión con MFA pendiente (venció o se perdió el segundo factor) no
    // cuenta como iniciada: se vuelve a pasar por contraseña y código.
    const pendingSecondFactor = user?.mfa.enabled === true && !user.mfa.session_verified;

    if (!isLoadingUser && user && !pendingSecondFactor) {
        return <Navigate to="/" replace />;
    }

    const onSubmit = handleSubmit((values) => {
        login.mutate(values, {
            onSuccess: (result) => {
                if (result.kind === 'challenge') setChallenge(result.challenge);
            },
        });
    });

    if (challenge) {
        return (
            <div>
                <div className="text-center">
                    <h2 className="text-[2rem] font-bold tracking-tight text-brand-700 shorter:text-[1.75rem] dark:text-white">
                        Verificación en dos pasos
                    </h2>
                    <p className="mt-3 text-muted-foreground shorter:mt-1.5">Confirma que eres tú para entrar.</p>
                </div>
                <div className="mt-10 short:mt-7 shorter:mt-5">
                    <MfaChallenge
                        challenge={challenge}
                        onRestart={() => {
                            setChallenge(null);
                            login.reset();
                        }}
                    />
                </div>
            </div>
        );
    }

    const serverErrors =
        login.error instanceof AxiosError && login.error.response?.status === 422
            ? (login.error.response.data.errors as Record<string, string[]>)
            : null;

    const invalidCredentials =
        login.error instanceof AxiosError && login.error.response?.status !== 422 ? login.error : null;

    const emailError = errors.email?.message ?? serverErrors?.email?.[0];

    return (
        <div>
            <div className="text-center">
                <h2 className="text-[2rem] font-bold tracking-tight text-brand-700 shorter:text-[1.75rem] dark:text-white">
                    Bienvenido de nuevo
                </h2>
                <p className="mt-3 text-muted-foreground shorter:mt-1.5">Inicia sesión y continúa conectando con tus clientes.</p>
            </div>

            <form onSubmit={onSubmit} className="mt-10 space-y-6 short:mt-7 shorter:mt-5 shorter:space-y-4" noValidate>
                <Field label="Correo electrónico" htmlFor="email" error={emailError} className="space-y-2.5">
                    <div className="relative">
                        <Mail className={iconClasses} />
                        <Input
                            id="email"
                            type="email"
                            autoComplete="email"
                            placeholder="tu@empresa.com"
                            className={inputClasses}
                            aria-invalid={Boolean(emailError)}
                            {...register('email')}
                        />
                    </div>
                </Field>

                <Field label="Contraseña" htmlFor="password" error={errors.password?.message} className="space-y-2.5">
                    <div className="relative">
                        <Lock className={iconClasses} />
                        <Input
                            id="password"
                            type={showPassword ? 'text' : 'password'}
                            autoComplete="current-password"
                            placeholder="Introduce tu contraseña"
                            className={`${inputClasses} pr-12`}
                            aria-invalid={Boolean(errors.password)}
                            {...register('password')}
                        />
                        <button
                            type="button"
                            onClick={() => setShowPassword((visible) => !visible)}
                            aria-label={showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                            className="absolute top-1/2 right-3 -translate-y-1/2 rounded-md p-1.5 text-muted-foreground hover:text-foreground"
                        >
                            {showPassword ? <EyeOff className="size-5" /> : <Eye className="size-5" />}
                        </button>
                    </div>
                </Field>

                <div className="flex items-center justify-end gap-4">
                    <Link
                        to="/forgot-password"
                        className="text-sm font-semibold text-tenant-accent hover:underline dark:text-prompt-400"
                    >
                        ¿Olvidaste tu contraseña?
                    </Link>
                </div>

                {invalidCredentials && (
                    <Alert variant="error">
                        <AlertCircle />
                        <AlertTitle>No pudimos iniciar sesión. Revisa tus datos e intenta de nuevo.</AlertTitle>
                    </Alert>
                )}

                <Button
                    type="submit"
                    loading={login.isPending}
                    className="h-14 w-full rounded-lg bg-tenant-accent text-base hover:bg-tenant-accent/90 shorter:h-12"
                >
                    {login.isPending ? 'Iniciando sesión…' : 'Iniciar sesión'}
                    {!login.isPending && <ArrowRight className="size-5" />}
                </Button>
            </form>

            <Separator className="my-8 short:my-6 shorter:my-4" />

            <p className="flex items-center justify-center gap-1.5 text-sm text-muted-foreground">
                ¿Aún no tienes una cuenta?
                <ComingSoon className="inline-flex items-center gap-1 font-semibold text-tenant-accent hover:underline dark:text-prompt-400">
                    Regístrate
                    <ArrowUpRight className="size-4" />
                </ComingSoon>
            </p>
        </div>
    );
}
