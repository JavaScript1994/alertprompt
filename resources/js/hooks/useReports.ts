import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { isoDate } from '@/lib/format';
import type { AdminReportSummary, AlertSeverity, AppAlert, PaginatedResponse, ReportSummary } from '@/types';

export interface DateRange {
    from: string;
    to: string;
}

export function useReport(range: DateRange) {
    return useQuery({
        queryKey: ['reports', range],
        queryFn: async () => {
            const { data } = await api.get<{ data: ReportSummary }>('/api/reports', { params: range });
            return data.data;
        },
        placeholderData: keepPreviousData,
    });
}

export function useAdminReport(range: DateRange) {
    return useQuery({
        queryKey: ['admin-reports', range],
        queryFn: async () => {
            const { data } = await api.get<{ data: AdminReportSummary }>('/api/admin/reports', { params: range });
            return data.data;
        },
        placeholderData: keepPreviousData,
    });
}

/** Alertas abiertas del propio tenant (dashboard). */
export function useMyAlerts() {
    return useQuery({
        queryKey: ['alerts'],
        queryFn: async () => {
            const { data } = await api.get<{ data: AppAlert[] }>('/api/alerts');
            return data.data;
        },
        refetchInterval: 30_000,
    });
}

export interface AlertFilters {
    status: 'open' | 'resolved';
    severity?: AlertSeverity;
    page: number;
}

export function useAdminAlerts(filters: AlertFilters) {
    return useQuery({
        queryKey: ['admin-alerts', filters],
        queryFn: async () => {
            const { data } = await api.get<PaginatedResponse<AppAlert>>('/api/admin/alerts', { params: { ...filters, per_page: 15 } });
            return data;
        },
        placeholderData: keepPreviousData,
        refetchInterval: 30_000,
    });
}

export function useResolveAlert() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (id: number) => {
            await api.post(`/api/admin/alerts/${id}/resolve`);
        },
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin-alerts'] }),
    });
}

/** Rango "últimos N días" en fechas locales YYYY-MM-DD. */
export function lastDays(days: number): DateRange {
    const to = new Date();
    const from = new Date();
    from.setDate(to.getDate() - (days - 1));

    return { from: isoDate(from), to: isoDate(to) };
}
