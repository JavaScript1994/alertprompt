import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { BulkImport, Client, PaginatedResponse } from '@/types';

const KEY = 'bulk-imports';

export function useBulkImports(page: number) {
    return useQuery({
        queryKey: [KEY, page],
        queryFn: async () => {
            const { data } = await api.get<PaginatedResponse<BulkImport>>('/api/admin/bulk-imports', { params: { page } });
            return data;
        },
        placeholderData: keepPreviousData,
        // Mientras haya cargas en curso, se refresca solo.
        refetchInterval: (query) =>
            query.state.data?.data.some((item) => item.status === 'queued' || item.status === 'processing') ? 3000 : false,
    });
}

export function useBulkImport(id: number | null) {
    return useQuery({
        queryKey: [KEY, 'detail', id],
        queryFn: async () => {
            const { data } = await api.get<{ data: BulkImport }>(`/api/admin/bulk-imports/${id}`);
            return data.data;
        },
        enabled: id !== null,
    });
}

export function useAttestationText() {
    return useQuery({
        queryKey: [KEY, 'attestation'],
        queryFn: async () => {
            const { data } = await api.get<{ data: { text: string } }>('/api/admin/bulk-imports/attestation');
            return data.data.text;
        },
        staleTime: Infinity,
    });
}

/** Todos los clientes (empresas y personas) para elegir destino. */
export function useAllClients() {
    return useQuery({
        queryKey: ['admin-clients', 'all'],
        queryFn: async () => {
            const { data } = await api.get<PaginatedResponse<Client>>('/api/admin/clients', { params: { per_page: 100, status: 'active' } });
            return data.data;
        },
    });
}

export interface UploadBulkInput {
    client_id: number;
    file: File;
    declared_source: string;
}

export function useUploadBulkImport() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (input: UploadBulkInput) => {
            const form = new FormData();
            form.append('client_id', String(input.client_id));
            form.append('file', input.file);
            form.append('declared_source', input.declared_source);
            form.append('attestation', '1');
            const { data } = await api.post<{ data: BulkImport }>('/api/admin/bulk-imports', form, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            return data.data;
        },
        onSuccess: () => queryClient.invalidateQueries({ queryKey: [KEY] }),
    });
}
