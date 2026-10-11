import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { PaginatedResponse } from '@/types';
import type { AddOn, AddOnInput, AddOnInterest, AddOnRequest, AddOnRequestStatus, TenantAddOn } from './types';

const KEY = 'admin-add-ons';

export function useAddOnCatalog(enabled = true) {
    return useQuery({
        queryKey: [KEY, 'catalog'],
        queryFn: async () => (await api.get<{ data: AddOn[] }>('/api/admin/add-ons')).data.data,
        enabled,
    });
}

export function useSaveAddOn() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, ...input }: AddOnInput & { id?: number }) =>
            (id ? await api.put<{ data: AddOn }>(`/api/admin/add-ons/${id}`, input) : await api.post<{ data: AddOn }>('/api/admin/add-ons', input)).data.data,
        onSuccess: () => queryClient.invalidateQueries({ queryKey: [KEY] }),
    });
}

export function useSetAddOnActive() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, active }: { id: number; active: boolean }) =>
            (await api.post<{ data: AddOn }>(`/api/admin/add-ons/${id}/${active ? 'activate' : 'deactivate'}`)).data.data,
        onSuccess: () => queryClient.invalidateQueries({ queryKey: [KEY] }),
    });
}

export function useAddOnInterests(id: number | null) {
    return useQuery({
        queryKey: [KEY, 'interests', id],
        queryFn: async () => (await api.get<{ data: AddOnInterest[] }>(`/api/admin/add-ons/${id}/interests`)).data.data,
        enabled: id !== null,
    });
}

export function useAddOnRequests(status: AddOnRequestStatus, page: number) {
    return useQuery({
        queryKey: [KEY, 'requests', status, page],
        queryFn: async () => (await api.get<PaginatedResponse<AddOnRequest>>('/api/admin/add-on-requests', { params: { status, page } })).data,
        placeholderData: keepPreviousData,
    });
}

export function useDecideAddOnRequest() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, approve, note }: { id: number; approve: boolean; note?: string }) =>
            (await api.post<{ data: AddOnRequest }>(`/api/admin/add-on-requests/${id}/${approve ? 'approve' : 'reject'}`, { note })).data.data,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: [KEY] });
            queryClient.invalidateQueries({ queryKey: ['admin-alerts'] });
        },
    });
}

export function useClientAddOns(clientId: number) {
    return useQuery({
        queryKey: [KEY, 'client', clientId],
        queryFn: async () => (await api.get<{ data: TenantAddOn[] }>(`/api/admin/clients/${clientId}/add-ons`)).data.data,
    });
}

function useClientAddOnMutation<TInput>(clientId: number, request: (input: TInput) => Promise<unknown>) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (input: TInput) => {
            await request(input);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: [KEY] });
            // El contrato prende o apaga un módulo del cliente.
            queryClient.invalidateQueries({ queryKey: ['admin-modules'] });
            queryClient.invalidateQueries({ queryKey: ['admin-clients'] });
        },
    });
}

export function useContractAddOn(clientId: number) {
    return useClientAddOnMutation(clientId, (addOnId: number) => api.post(`/api/admin/clients/${clientId}/add-ons`, { add_on_id: addOnId }));
}

export function useCancelContract(clientId: number) {
    return useClientAddOnMutation(clientId, ({ id, reason }: { id: number; reason: string }) =>
        api.post(`/api/admin/clients/${clientId}/add-ons/${id}/cancel`, { reason }),
    );
}
