import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { PaginatedResponse } from '@/types';
import type { AdminAnnouncement, Announcement, AnnouncementInput, AnnouncementStat } from './types';

const MINE = ['announcements'];
const ADMIN = 'admin-announcements';

// ── Del usuario (campana, banners, Novedades) ─────────────────

/** Se refresca cada minuto y al volver a la pestaña: un incidente debe verse pronto. */
export function useAnnouncements(enabled = true) {
    return useQuery({
        queryKey: MINE,
        queryFn: async () => {
            const { data } = await api.get<{ data: Announcement[] }>('/api/announcements');
            return data.data;
        },
        enabled,
        refetchInterval: 60_000,
        staleTime: 30_000,
    });
}

/** Marca en la caché al instante; el servidor confirma después. */
function useMarkLocally() {
    const queryClient = useQueryClient();
    return (ids: number[] | 'all', patch: Partial<Pick<Announcement, 'read_at' | 'dismissed_at'>>) =>
        queryClient.setQueryData<Announcement[]>(MINE, (items) =>
            items?.map((item) =>
                ids === 'all' || ids.includes(item.id)
                    ? { ...item, read_at: item.read_at ?? patch.read_at ?? null, dismissed_at: patch.dismissed_at ?? item.dismissed_at }
                    : item,
            ),
        );
}

export function useMarkAnnouncementsRead() {
    const mark = useMarkLocally();

    return useMutation({
        mutationFn: async (ids: number[]) => {
            await Promise.all(ids.map((id) => api.post(`/api/announcements/${id}/read`)));
        },
        onMutate: (ids) => mark(ids, { read_at: new Date().toISOString() }),
    });
}

export function useDismissAnnouncement() {
    const mark = useMarkLocally();

    return useMutation({
        mutationFn: async (id: number) => {
            await api.post(`/api/announcements/${id}/dismiss`);
        },
        onMutate: (id) => {
            const now = new Date().toISOString();
            mark([id], { read_at: now, dismissed_at: now });
        },
    });
}

export function useMarkAllAnnouncementsRead() {
    const mark = useMarkLocally();

    return useMutation({
        mutationFn: async () => {
            await api.post('/api/announcements/read-all');
        },
        onMutate: () => mark('all', { read_at: new Date().toISOString() }),
    });
}

// ── Administración ────────────────────────────────────────────

export function useAdminAnnouncements(page: number) {
    return useQuery({
        queryKey: [ADMIN, page],
        queryFn: async () => {
            const { data } = await api.get<PaginatedResponse<AdminAnnouncement>>('/api/admin/announcements', { params: { page } });
            return data;
        },
        placeholderData: keepPreviousData,
    });
}

export function useAnnouncementStats(id: number | null) {
    return useQuery({
        queryKey: [ADMIN, 'stats', id],
        queryFn: async () => {
            const { data } = await api.get<{ data: AnnouncementStat[] }>(`/api/admin/announcements/${id}/stats`);
            return data.data;
        },
        enabled: id !== null,
    });
}

function useRefreshAdmin() {
    const queryClient = useQueryClient();
    return () => queryClient.invalidateQueries({ queryKey: [ADMIN] });
}

export function useSaveAnnouncement(id?: number) {
    const refresh = useRefreshAdmin();

    return useMutation({
        mutationFn: async (input: AnnouncementInput) => {
            const { data } = id
                ? await api.put<{ data: AdminAnnouncement }>(`/api/admin/announcements/${id}`, input)
                : await api.post<{ data: AdminAnnouncement }>('/api/admin/announcements', input);
            return data.data;
        },
        onSuccess: refresh,
    });
}

export function useResolveAnnouncement() {
    const refresh = useRefreshAdmin();

    return useMutation({
        mutationFn: async (id: number) => {
            await api.post(`/api/admin/announcements/${id}/resolve`);
        },
        onSuccess: refresh,
    });
}

export function useDeleteAnnouncement() {
    const refresh = useRefreshAdmin();

    return useMutation({
        mutationFn: async (id: number) => {
            await api.delete(`/api/admin/announcements/${id}`);
        },
        onSuccess: refresh,
    });
}

/** Clientes para elegir destinatarios (búsqueda por nombre o documento). */
export function useClientOptions(search: string) {
    return useQuery({
        queryKey: ['admin-clients', 'options', search],
        queryFn: async () => {
            const { data } = await api.get<{ data: { id: number; name: string; plan_name: string }[] }>('/api/admin/clients', {
                params: { per_page: 100, search: search || undefined },
            });
            return data.data;
        },
        placeholderData: keepPreviousData,
    });
}
