import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { AccountChannel, AdminChannelAccountSlot, ChannelAccount, ChannelAccountSummary } from '@/types';

const KEY = 'channel-accounts';

export function useChannelAccounts() {
    return useQuery({
        queryKey: [KEY],
        queryFn: async () => {
            const { data } = await api.get<{ data: ChannelAccountSummary[] }>('/api/channel-accounts');
            return data.data;
        },
    });
}

export function useRequestChannelAccount() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (input: { channel: AccountChannel; sender: string; display_name: string | null }) => {
            const { data } = await api.post<{ data: ChannelAccountSummary[] }>('/api/channel-accounts/request', input);
            return data.data;
        },
        onSuccess: (data) => queryClient.setQueryData([KEY], data),
    });
}

export function useClientChannelAccounts(clientId: number) {
    return useQuery({
        queryKey: [KEY, 'admin', clientId],
        queryFn: async () => {
            const { data } = await api.get<{ data: AdminChannelAccountSlot[] }>(`/api/admin/clients/${clientId}/channel-accounts`);
            return data.data;
        },
    });
}

export interface ConfigureChannelAccountInput {
    provider: string;
    sender: string;
    display_name: string | null;
    status: string;
    quality_rating: string | null;
    messaging_tier: string | null;
    notes: string | null;
    credentials: Record<string, string>;
}

export function useConfigureChannelAccount(clientId: number, channel: AccountChannel) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (input: ConfigureChannelAccountInput) => {
            const { data } = await api.put<{ data: ChannelAccount }>(`/api/admin/clients/${clientId}/channel-accounts/${channel}`, input);
            return data.data;
        },
        onSuccess: () => queryClient.invalidateQueries({ queryKey: [KEY, 'admin', clientId] }),
    });
}
