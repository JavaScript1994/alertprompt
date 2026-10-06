import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { PaginatedResponse, Template, TemplateCategory, TemplateChannel, TemplatePreview } from '@/types';

const TEMPLATES_QUERY_KEY = 'templates';

// Historial de plantillas en datatable paginado: 5 por página para no
// desbordar el layout, igual que en Campañas.
const TEMPLATES_PER_PAGE = 5;

export interface TemplateFormInput {
    channel: TemplateChannel;
    category: TemplateCategory;
    name: string;
    body: string;
}

export function useTemplates(page: number) {
    return useQuery({
        queryKey: [TEMPLATES_QUERY_KEY, { page }],
        queryFn: async () => {
            const { data } = await api.get<PaginatedResponse<Template>>('/api/templates', {
                params: { page, per_page: TEMPLATES_PER_PAGE },
            });

            return data;
        },
        placeholderData: (previousData) => previousData,
    });
}

export function useCreateTemplate() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (input: TemplateFormInput) => {
            const { data } = await api.post<{ data: Template }>('/api/templates', input);

            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: [TEMPLATES_QUERY_KEY] });
        },
    });
}

export function useUpdateTemplate() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, ...input }: TemplateFormInput & { id: number; status?: string }) => {
            const { data } = await api.put<{ data: Template }>(`/api/templates/${id}`, input);

            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: [TEMPLATES_QUERY_KEY] });
        },
    });
}

export function useDeleteTemplate() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (id: number) => {
            await api.delete(`/api/templates/${id}`);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: [TEMPLATES_QUERY_KEY] });
        },
    });
}

export function useApprovedTemplates() {
    return useQuery({
        queryKey: [TEMPLATES_QUERY_KEY, { status: 'approved' }],
        queryFn: async () => {
            const { data } = await api.get<PaginatedResponse<Template>>('/api/templates', {
                params: { status: 'approved', per_page: 100 },
            });

            return data.data;
        },
    });
}

export function usePreviewTemplate() {
    return useMutation({
        mutationFn: async ({ body, sampleData }: { body: string; sampleData: Record<string, string> }) => {
            const { data } = await api.post<TemplatePreview>('/api/templates/preview', {
                body,
                sample_data: sampleData,
            });

            return data;
        },
    });
}
