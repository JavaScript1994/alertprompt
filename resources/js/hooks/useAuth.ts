import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { AxiosError } from 'axios';
import { api, ensureCsrfCookie } from '@/lib/api';
import type { LoginChallenge } from '@/features/mfa/types';
import type { User } from '@/types';

interface Envelope<T> {
    data: T;
}

interface LoginPayload {
    email: string;
    password: string;
}

/** Con MFA la contraseña no abre sesión: devuelve el reto del segundo paso. */
export type LoginResult = { kind: 'user'; user: User } | { kind: 'challenge'; challenge: LoginChallenge };

const AUTH_USER_QUERY_KEY = ['auth', 'user'];

async function fetchAuthenticatedUser(): Promise<User | null> {
    try {
        const { data } = await api.get<Envelope<User>>('/api/user');
        return data.data;
    } catch (error) {
        if (error instanceof AxiosError && error.response?.status === 401) {
            return null;
        }
        throw error;
    }
}

export function useAuthUser() {
    return useQuery({
        queryKey: AUTH_USER_QUERY_KEY,
        queryFn: fetchAuthenticatedUser,
        staleTime: 5 * 60 * 1000,
        retry: false,
    });
}

export function useLogin() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: LoginPayload): Promise<LoginResult> => {
            await ensureCsrfCookie();
            const { data } = await api.post<Envelope<User> | LoginChallenge>('/api/login', payload);
            return 'mfa_required' in data ? { kind: 'challenge', challenge: data } : { kind: 'user', user: data.data };
        },
        onSuccess: (result) => {
            if (result.kind === 'user') queryClient.setQueryData(AUTH_USER_QUERY_KEY, result.user);
        },
    });
}

export function useLogout() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async () => {
            await api.post('/api/logout');
        },
        onSuccess: () => {
            queryClient.setQueryData(AUTH_USER_QUERY_KEY, null);
        },
    });
}

export function useForgotPassword() {
    return useMutation({
        mutationFn: async (email: string) => {
            await ensureCsrfCookie();
            const { data } = await api.post<{ message: string }>('/api/forgot-password', { email });
            return data.message;
        },
    });
}

export interface ResetPasswordPayload {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
    invite: boolean;
}

export function useResetPassword() {
    return useMutation({
        mutationFn: async (payload: ResetPasswordPayload) => {
            await ensureCsrfCookie();
            const { data } = await api.post<{ message: string }>('/api/reset-password', payload);
            return data.message;
        },
    });
}
