import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { ChannelUsage, Membership, MembershipOverview } from '@/types';

const KEY = 'memberships';

export function useMyMembership() {
    return useQuery({
        queryKey: [KEY, 'mine'],
        queryFn: async () => {
            const { data } = await api.get<{ data: MembershipOverview }>('/api/membership');
            return data.data;
        },
    });
}

export function useClientMemberships(clientId: number) {
    return useQuery({
        queryKey: [KEY, 'client', clientId],
        queryFn: async () => {
            const { data } = await api.get<{ data: Membership[]; usage: ChannelUsage }>(`/api/admin/clients/${clientId}/memberships`);
            return data;
        },
    });
}

export interface MembershipInput {
    plan: string;
    billing_cycle: string;
    price: string;
    starts_at: string;
    ends_at: string;
    quotas: Record<string, number | null>;
    contract_reference: string | null;
    notes: string | null;
}

export function useCreateMembership(clientId: number) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (input: MembershipInput) => {
            const { data } = await api.post<{ data: Membership }>(`/api/admin/clients/${clientId}/memberships`, input);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: [KEY, 'client', clientId] });
            queryClient.invalidateQueries({ queryKey: ['admin-clients'] });
        },
    });
}

export function useCancelMembership(clientId: number) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, reason }: { id: number; reason?: string }) => {
            await api.post(`/api/admin/clients/${clientId}/memberships/${id}/cancel`, { reason });
        },
        onSuccess: () => queryClient.invalidateQueries({ queryKey: [KEY, 'client', clientId] }),
    });
}
