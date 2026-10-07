import { zodResolver } from '@hookform/resolvers/zod';
import { AxiosError } from 'axios';
import { AlertCircle } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Navigate } from 'react-router-dom';
import { z } from 'zod';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAuthUser, useLogin } from '@/hooks/useAuth';

const loginSchema = z.object({
    email: z.string().min(1, 'Ingresa tu email').email('Email inválido'),
    password: z.string().min(1, 'Ingresa tu contraseña'),
});

type LoginForm = z.infer<typeof loginSchema>;

export default function Login() {
    const { data: user, isLoading: isLoadingUser } = useAuthUser();
    const login = useLogin();
    const [remember, setRemember] = useState(true);

    const {
        register,
        handleSubmit,
        formState: { errors },
    } = useForm<LoginForm>({
        resolver: zodResolver(loginSchema),
    });

    if (!isLoadingUser && user) {
        return <Navigate to="/" replace />;
    }

    const onSubmit = handleSubmit((values) => {
        login.mutate({ ...values, remember });
    });

    const serverErrors =
        login.error instanceof AxiosError && login.error.response?.status === 422
            ? (login.error.response.data.errors as Record<string, string[]>)
            : null;

    const invalidCredentials =
        login.error instanceof AxiosError && login.error.response?.status !== 422 ? login.error : null;

    const emailError = errors.email?.message ?? serverErrors?.email?.[0];

    return (
        <div>
            <h2 className="text-2xl tracking-tight">Bienvenido de nuevo</h2>
            <p className="mt-1.5 mb-8 text-sm text-muted-foreground">Ingresá con las credenciales de tu cuenta.</p>

            <form onSubmit={onSubmit} className="space-y-4" noValidate>
                <Field label="Email" htmlFor="email" error={emailError}>
                    <Input
                        id="email"
                        type="email"
                        autoComplete="email"
                        placeholder="vos@empresa.pe"
                        aria-invalid={Boolean(emailError)}
                        {...register('email')}
                    />
                </Field>

                <Field label="Contraseña" htmlFor="password" error={errors.password?.message}>
                    <Input
                        id="password"
                        type="password"
                        autoComplete="current-password"
                        aria-invalid={Boolean(errors.password)}
                        {...register('password')}
                    />
                </Field>

                <div className="flex items-center justify-between py-1">
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="remember"
                            checked={remember}
                            onCheckedChange={(checked) => setRemember(checked === true)}
                        />
                        <Label htmlFor="remember" className="cursor-pointer font-normal text-muted-foreground">
                            Recordarme
                        </Label>
                    </div>
                    <a href="#" className="text-sm font-medium text-primary hover:underline dark:text-brand-200">
                        ¿Olvidaste tu contraseña?
                    </a>
                </div>

                {invalidCredentials && (
                    <Alert variant="error">
                        <AlertCircle />
                        <AlertTitle>No pudimos iniciar sesión. Intentá de nuevo.</AlertTitle>
                    </Alert>
                )}

                <Button type="submit" loading={login.isPending} className="w-full">
                    {login.isPending ? 'Ingresando…' : 'Ingresar'}
                </Button>
            </form>
        </div>
    );
}
