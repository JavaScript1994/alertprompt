import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { Contact, PaginatedResponse } from '@/types';

interface UseContactsParams {
    page: number;
    search: string;
}

const CONTACTS_QUERY_KEY = 'contacts';

export function useContacts({ page, search }: UseContactsParams) {
    return useQuery({
        queryKey: [CONTACTS_QUERY_KEY, { page, search }],
        queryFn: async () => {
            const { data } = await api.get<PaginatedResponse<Contact>>('/api/contacts', {
                params: { page, search: search || undefined },
            });

            return data;
        },
        placeholderData: (previousData) => previousData,
    });
}

interface CreateContactInput {
    name: string;
    phone?: string;
    email?: string;
}

export function useCreateContact() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (input: CreateContactInput) => {
            const { data } = await api.post<{ data: Contact }>('/api/contacts', input);

            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: [CONTACTS_QUERY_KEY] });
        },
    });
}

interface UpdateContactInput {
    id: number;
    name: string;
    phone?: string;
    email?: string;
}

export function useUpdateContact() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, ...input }: UpdateContactInput) => {
            const { data } = await api.put<{ data: Contact }>(`/api/contacts/${id}`, input);

            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: [CONTACTS_QUERY_KEY] });
        },
    });
}

export function useImportContacts() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (file: File) => {
            const formData = new FormData();
            formData.append('file', file);

            await api.post('/api/contacts/import', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
        },
        onSuccess: () => {
            // El import corre en cola (Horizon); refrescamos ahora y de nuevo
            // en unos segundos para reflejar el resultado sin polling constante.
            queryClient.invalidateQueries({ queryKey: [CONTACTS_QUERY_KEY] });
            setTimeout(() => {
                queryClient.invalidateQueries({ queryKey: [CONTACTS_QUERY_KEY] });
            }, 2500);
        },
    });
}
