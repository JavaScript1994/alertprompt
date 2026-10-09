import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { AssignableRole, Tenant, TenantUser } from '@/types';

const USERS_QUERY_KEY = 'tenant-users';
const ACCOUNT_QUERY_KEY = 'account-settings';

export function useTenantUsers() {
    return useQuery({
        queryKey: [USERS_QUERY_KEY],
        queryFn: async () => {
            const { data } = await api.get<{ data: TenantUser[] }>('/api/users');
            return data.data;
        },
    });
}

export function useAssignableRoles() {
    return useQuery({
        queryKey: [USERS_QUERY_KEY, 'roles'],
        queryFn: async () => {
            const { data } = await api.get<{ data: AssignableRole[] }>('/api/users/roles');
            return data.data;
        },
        staleTime: 5 * 60 * 1000,
    });
}

export interface UserFormInput {
    name: string;
    role_id: number;
}

export function useInviteUser() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (input: UserFormInput & { email: string }) => {
            const { data } = await api.post<{ data: TenantUser }>('/api/users', input);
            return data.data;
        },
        onSuccess: () => queryClient.invalidateQueries({ queryKey: [USERS_QUERY_KEY] }),
    });
}

export function useUpdateUser() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, ...input }: UserFormInput & { id: number }) => {
            const { data } = await api.put<{ data: TenantUser }>(`/api/users/${id}`, input);
            return data.data;
        },
        onSuccess: () => queryClient.invalidateQueries({ queryKey: [USERS_QUERY_KEY] }),
    });
}

export function useSetUserActive() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, active }: { id: number; active: boolean }) => {
            const { data } = await api.post<{ data: TenantUser }>(`/api/users/${id}/${active ? 'reactivate' : 'deactivate'}`);
            return data.data;
        },
        onSuccess: () => queryClient.invalidateQueries({ queryKey: [USERS_QUERY_KEY] }),
    });
}

export function useResendInvitation() {
    return useMutation({
        mutationFn: async (id: number) => {
            await api.post(`/api/users/${id}/resend-invitation`);
        },
    });
}

export function useAccountSettings() {
    return useQuery({
        queryKey: [ACCOUNT_QUERY_KEY],
        queryFn: async () => {
            const { data } = await api.get<{ data: Tenant }>('/api/settings/account');
            return data.data;
        },
    });
}

export interface AccountSettingsInput {
    name: string;
    contact_email: string | null;
    contact_phone: string | null;
    address: string | null;
    timezone: string;
}

export function useUpdateAccountSettings() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (input: AccountSettingsInput) => {
            const { data } = await api.put<{ data: Tenant }>('/api/settings/account', input);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: [ACCOUNT_QUERY_KEY] });
            // El nombre de la cuenta aparece en el sidebar.
            queryClient.invalidateQueries({ queryKey: ['auth', 'user'] });
        },
    });
}
