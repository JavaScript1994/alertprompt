import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { Campaign, PaginatedResponse } from '@/types';

const CAMPAIGNS_QUERY_KEY = 'campaigns';

// Dashboard en vivo (CLAUDE.md día 7): polling 3s, no websockets.
const LIVE_POLL_INTERVAL_MS = 3000;

// Historial de campañas en datatable paginado: 5 por página para no
// desbordar el layout con el listado de campañas.
const CAMPAIGNS_PER_PAGE = 5;

export function useCampaigns(page: number) {
    return useQuery({
        queryKey: [CAMPAIGNS_QUERY_KEY, { page }],
        queryFn: async () => {
            const { data } = await api.get<PaginatedResponse<Campaign>>('/api/campaigns', {
                params: { page, per_page: CAMPAIGNS_PER_PAGE },
            });

            return data;
        },
        placeholderData: (previousData) => previousData,
        refetchInterval: LIVE_POLL_INTERVAL_MS,
    });
}

export function useCampaign(id: number | null) {
    return useQuery({
        queryKey: [CAMPAIGNS_QUERY_KEY, 'detail', id],
        queryFn: async () => {
            const { data } = await api.get<{ data: Campaign }>(`/api/campaigns/${id}`);

            return data.data;
        },
        enabled: id !== null,
    });
}

export function useCreateCampaign() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (input: {
            name: string;
            template_id: number;
            contact_ids: number[];
            scheduled_at?: string;
        }) => {
            const { data } = await api.post<{ data: Campaign }>('/api/campaigns', input);

            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: [CAMPAIGNS_QUERY_KEY] });
        },
    });
}

export function useUpdateCampaign() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({
            id,
            ...input
        }: {
            id: number;
            name: string;
            template_id: number;
            contact_ids: number[];
            scheduled_at?: string;
        }) => {
            const { data } = await api.put<{ data: Campaign }>(`/api/campaigns/${id}`, input);

            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: [CAMPAIGNS_QUERY_KEY] });
        },
    });
}

export function useDeleteCampaign() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (id: number) => {
            await api.delete(`/api/campaigns/${id}`);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: [CAMPAIGNS_QUERY_KEY] });
        },
    });
}

export function useDispatchCampaign() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (id: number) => {
            const { data } = await api.post<{ data: Campaign }>(`/api/campaigns/${id}/dispatch`);

            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: [CAMPAIGNS_QUERY_KEY] });
        },
    });
}
