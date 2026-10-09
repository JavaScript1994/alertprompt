import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { ModuleKey, ModuleSummary } from '@/types';

const MODULES_QUERY_KEY = 'admin-modules';

export function useModuleCatalog() {
    return useQuery({
        queryKey: [MODULES_QUERY_KEY],
        queryFn: async () => {
            const { data } = await api.get<{ data: ModuleSummary[] }>('/api/admin/modules');
            return data.data;
        },
    });
}

export function useClientModules(clientId: number) {
    return useQuery({
        queryKey: [MODULES_QUERY_KEY, 'client', clientId],
        queryFn: async () => {
            const { data } = await api.get<{ data: ModuleKey[] }>(`/api/admin/clients/${clientId}/modules`);
            return data.data;
        },
    });
}

export function useUpdateClientModules(clientId: number) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (modules: ModuleKey[]) => {
            const { data } = await api.put<{ data: ModuleKey[] }>(`/api/admin/clients/${clientId}/modules`, { modules });
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: [MODULES_QUERY_KEY] });
            queryClient.invalidateQueries({ queryKey: ['admin-clients'] });
        },
    });
}
