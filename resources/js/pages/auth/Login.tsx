import { zodResolver } from '@hookform/resolvers/zod';
import { AxiosError } from 'axios';
import { Lock, Mail } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Navigate } from 'react-router-dom';
import { z } from 'zod';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';
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

    return (
        <div>
            <h2 className="text-2xl font-semibold tracking-tight text-ink-900">Bienvenido de nuevo</h2>
            <p className="mt-1.5 mb-8 text-sm text-slate-500">Ingresá con las credenciales de tu cuenta.</p>

            <form onSubmit={onSubmit} className="space-y-4" noValidate>
                <Input
                    type="email"
                    label="Email"
                    autoComplete="email"
                    icon={Mail}
                    placeholder="vos@empresa.pe"
                    error={errors.email?.message ?? serverErrors?.email?.[0]}
                    {...register('email')}
                />

                <Input
                    type="password"
                    label="Contraseña"
                    autoComplete="current-password"
                    icon={Lock}
                    error={errors.password?.message}
                    {...register('password')}
                />

                <div className="flex items-center justify-between">
                    <label className="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                        <input
                            type="checkbox"
                            checked={remember}
                            onChange={(event) => setRemember(event.target.checked)}
                            className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-2 focus:ring-brand-500/30"
                        />
                        Recordarme
                    </label>
                    <a href="#" className="text-sm font-medium text-brand-600 hover:text-brand-500">
                        ¿Olvidaste tu contraseña?
                    </a>
                </div>

                {invalidCredentials && <Alert type="error">No pudimos iniciar sesión. Intentá de nuevo.</Alert>}

                <Button type="submit" loading={login.isPending} className="w-full">
                    {login.isPending ? 'Ingresando…' : 'Ingresar'}
                </Button>
            </form>
        </div>
    );
}
