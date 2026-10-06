import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { AxiosError } from 'axios';
import { api, ensureCsrfCookie } from '@/lib/api';
import type { User } from '@/types';

interface Envelope<T> {
    data: T;
}

interface LoginPayload {
    email: string;
    password: string;
    remember?: boolean;
}

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
        mutationFn: async (payload: LoginPayload) => {
            await ensureCsrfCookie();
            const { data } = await api.post<Envelope<User>>('/api/login', payload);
            return data.data;
        },
        onSuccess: (user) => {
            queryClient.setQueryData(AUTH_USER_QUERY_KEY, user);
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
