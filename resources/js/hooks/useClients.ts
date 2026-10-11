import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type {
    AuditLogEntry,
    Campaign,
    Client,
    Contact,
    PaginatedResponse,
    Template,
    TenantStatus,
    TenantType,
    TenantUser,
} from '@/types';

const CLIENTS_QUERY_KEY = 'admin-clients';

export interface ClientFilters {
    type: TenantType;
    page: number;
    search?: string;
    status?: TenantStatus;
}

export interface ClientProfileInput {
    name: string;
    document_type: string;
    document_number: string;
    contact_email: string | null;
    contact_phone: string | null;
    address: string | null;
}

export interface CreateClientInput extends ClientProfileInput {
    type: TenantType;
    plan: string;
    admin_first_name: string;
    admin_last_name: string;
    admin_email: string;
    admin_job_title: string | null;
    admin_birth_date: string | null;
    admin_phone: string | null;
    admin_mobile: string | null;
    admin_photo: File | null;
}

export function useClients(filters: ClientFilters) {
    return useQuery({
        queryKey: [CLIENTS_QUERY_KEY, filters],
        queryFn: async () => {
            const { data } = await api.get<PaginatedResponse<Client>>('/api/admin/clients', {
                params: { ...filters, per_page: 10 },
            });
            return data;
        },
        placeholderData: keepPreviousData,
    });
}

export function useClient(id: number) {
    return useQuery({
        queryKey: [CLIENTS_QUERY_KEY, 'detail', id],
        queryFn: async () => {
            const { data } = await api.get<{ data: Client }>(`/api/admin/clients/${id}`);
            return data.data;
        },
    });
}

export function useCreateClient() {
    const queryClient = useQueryClient();

    return useMutation({
        // multipart: lleva la foto del administrador.
        mutationFn: async (input: CreateClientInput) => {
            const form = new FormData();
            for (const [key, value] of Object.entries(input)) {
                if (value === null || value === undefined || value === '') continue;
                form.append(key, value instanceof File ? value : String(value));
            }
            const { data } = await api.post<{ data: Client }>('/api/admin/clients', form);
            return data.data;
        },
        onSuccess: () => queryClient.invalidateQueries({ queryKey: [CLIENTS_QUERY_KEY] }),
    });
}

export function useUpdateClient(id: number) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (input: ClientProfileInput) => {
            const { data } = await api.put<{ data: Client }>(`/api/admin/clients/${id}`, input);
            return data.data;
        },
        onSuccess: () => queryClient.invalidateQueries({ queryKey: [CLIENTS_QUERY_KEY] }),
    });
}

export function useSetClientStatus(id: number) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ suspend, reason }: { suspend: boolean; reason?: string }) => {
            const action = suspend ? 'suspend' : 'reactivate';
            const { data } = await api.post<{ data: Client }>(`/api/admin/clients/${id}/${action}`, { reason });
            return data.data;
        },
        onSuccess: () => queryClient.invalidateQueries({ queryKey: [CLIENTS_QUERY_KEY] }),
    });
}

export function useClientUsers(id: number) {
    return useQuery({
        queryKey: [CLIENTS_QUERY_KEY, id, 'users'],
        queryFn: async () => {
            const { data } = await api.get<{ data: TenantUser[] }>(`/api/admin/clients/${id}/users`);
            return data.data;
        },
    });
}

export function useClientActivity(id: number, page: number) {
    return useQuery({
        queryKey: [CLIENTS_QUERY_KEY, id, 'activity', page],
        queryFn: async () => {
            const { data } = await api.get<PaginatedResponse<AuditLogEntry>>(`/api/admin/clients/${id}/activity`, {
                params: { page, per_page: 10 },
            });
            return data;
        },
        placeholderData: keepPreviousData,
    });
}

interface SupervisionResources {
    contacts: Contact;
    templates: Template;
    campaigns: Campaign;
}

/** Listados de solo lectura con los datos del cliente. */
export function useSupervision<K extends keyof SupervisionResources>(id: number, resource: K, page: number) {
    return useQuery({
        queryKey: [CLIENTS_QUERY_KEY, id, 'supervision', resource, page],
        queryFn: async () => {
            const { data } = await api.get<PaginatedResponse<SupervisionResources[K]>>(
                `/api/admin/clients/${id}/supervision/${resource}`,
                { params: { page, per_page: 10 } },
            );
            return data;
        },
        placeholderData: keepPreviousData,
    });
}

/**
 * Entrar o salir del panel de un cliente. Lo cacheado pertenece al otro
 * tenant y se descarta; la sesión (auth/user) solo se refresca, sin volver a
 * "cargando", para no desmontar el layout a mitad de la navegación.
 */
export function useImpersonation() {
    const queryClient = useQueryClient();

    const switchTenant = () => {
        queryClient.removeQueries({ predicate: (query) => query.queryKey[0] !== 'auth' });
        return queryClient.invalidateQueries({ queryKey: ['auth', 'user'] });
    };

    const start = useMutation({
        mutationFn: async (clientId: number) => {
            await api.post(`/api/admin/clients/${clientId}/impersonate`);
        },
        onSuccess: switchTenant,
    });

    const stop = useMutation({
        mutationFn: async () => {
            await api.delete('/api/admin/impersonation');
        },
        onSuccess: switchTenant,
    });

    return { start, stop };
}
