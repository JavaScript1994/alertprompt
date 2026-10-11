import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { ClientAddOn } from './types';

const KEY = ['add-ons', 'mine'];

export function useMyAddOns() {
    return useQuery({
        queryKey: KEY,
        queryFn: async () => (await api.get<{ data: ClientAddOn[] }>('/api/add-ons')).data.data,
    });
}

function useAddOnMutation<TInput>(request: (input: TInput) => Promise<unknown>) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (input: TInput) => {
            await request(input);
        },
        onSuccess: () => queryClient.invalidateQueries({ queryKey: KEY }),
    });
}

export function useRequestAddOn() {
    return useAddOnMutation(({ id, comment }: { id: number; comment: string | null }) => api.post(`/api/add-ons/${id}/request`, { comment }));
}

export function useCancelAddOnRequest() {
    return useAddOnMutation((id: number) => api.delete(`/api/add-ons/${id}/request`));
}

export function useAddOnInterest() {
    return useAddOnMutation(({ id, interested }: { id: number; interested: boolean }) =>
        interested ? api.post(`/api/add-ons/${id}/interest`) : api.delete(`/api/add-ons/${id}/interest`),
    );
}
