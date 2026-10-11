import { useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { TenantUser, User } from '@/types';
import type { PersonalDataOutput } from './schemas';

const AUTH_USER_QUERY_KEY = ['auth', 'user'];
const USERS_QUERY_KEY = ['tenant-users'];

interface Envelope<T> {
    data: T;
}

function photoForm(file: File): FormData {
    const form = new FormData();
    form.append('photo', file);
    return form;
}

// ── Mi perfil ─────────────────────────────────────────────────

function useSetMe() {
    const queryClient = useQueryClient();
    return (user: User) => queryClient.setQueryData(AUTH_USER_QUERY_KEY, user);
}

export function useUpdateProfile() {
    const setMe = useSetMe();

    return useMutation({
        mutationFn: async (input: PersonalDataOutput) => {
            const { data } = await api.put<Envelope<User>>('/api/profile', input);
            return data.data;
        },
        onSuccess: setMe,
    });
}

export function useUploadProfilePhoto() {
    const setMe = useSetMe();

    return useMutation({
        mutationFn: async (file: File) => {
            const { data } = await api.post<Envelope<User>>('/api/profile/photo', photoForm(file));
            return data.data;
        },
        onSuccess: setMe,
    });
}

export function useDeleteProfilePhoto() {
    const setMe = useSetMe();

    return useMutation({
        mutationFn: async () => {
            const { data } = await api.delete<Envelope<User>>('/api/profile/photo');
            return data.data;
        },
        onSuccess: setMe,
    });
}

// ── Usuarios de la cuenta (administrador) ─────────────────────

function useRefreshUsers() {
    const queryClient = useQueryClient();
    return () => queryClient.invalidateQueries({ queryKey: USERS_QUERY_KEY });
}

export function useUpdateUserPersonalData(userId: number) {
    const refresh = useRefreshUsers();

    return useMutation({
        mutationFn: async (input: PersonalDataOutput) => {
            const { data } = await api.put<Envelope<TenantUser>>(`/api/users/${userId}`, input);
            return data.data;
        },
        onSuccess: refresh,
    });
}

export function useUpdateUserRole(userId: number) {
    const refresh = useRefreshUsers();

    return useMutation({
        mutationFn: async (roleId: number) => {
            const { data } = await api.put<Envelope<TenantUser>>(`/api/users/${userId}/role`, { role_id: roleId });
            return data.data;
        },
        onSuccess: refresh,
    });
}

export function useChangeUserEmail(userId: number) {
    const refresh = useRefreshUsers();

    return useMutation({
        mutationFn: async (email: string) => {
            const { data } = await api.post<Envelope<TenantUser>>(`/api/users/${userId}/email`, { email });
            return data.data;
        },
        onSuccess: refresh,
    });
}

export function useUploadUserPhoto(userId: number) {
    const refresh = useRefreshUsers();

    return useMutation({
        mutationFn: async (file: File) => {
            const { data } = await api.post<Envelope<TenantUser>>(`/api/users/${userId}/photo`, photoForm(file));
            return data.data;
        },
        onSuccess: refresh,
    });
}

export function useDeleteUserPhoto(userId: number) {
    const refresh = useRefreshUsers();

    return useMutation({
        mutationFn: async () => {
            const { data } = await api.delete<Envelope<TenantUser>>(`/api/users/${userId}/photo`);
            return data.data;
        },
        onSuccess: refresh,
    });
}
