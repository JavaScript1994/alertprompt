import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { BillingSummary, CreditNote, Invoice, PaginatedResponse, PaymentRecord, SavedPaymentMethod } from '@/types';

const KEY = 'billing';

export function useBillingSummary() {
    return useQuery({
        queryKey: [KEY, 'summary'],
        queryFn: async () => (await api.get<{ data: BillingSummary }>('/api/billing/summary')).data.data,
    });
}

export function useMyInvoices(page: number) {
    return useQuery({
        queryKey: [KEY, 'invoices', page],
        queryFn: async () => (await api.get<PaginatedResponse<Invoice>>('/api/billing/invoices', { params: { page } })).data,
        placeholderData: keepPreviousData,
    });
}

export function useMyPayments(page: number) {
    return useQuery({
        queryKey: [KEY, 'payments', page],
        queryFn: async () => (await api.get<PaginatedResponse<PaymentRecord>>('/api/billing/payments', { params: { page } })).data,
        placeholderData: keepPreviousData,
    });
}

export function usePaymentMethods() {
    return useQuery({
        queryKey: [KEY, 'methods'],
        queryFn: async () => (await api.get<{ data: SavedPaymentMethod[] }>('/api/billing/payment-methods')).data.data,
    });
}

export function useRemovePaymentMethod() {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: async (id: number) => {
            await api.delete(`/api/billing/payment-methods/${id}`);
        },
        onSuccess: () => queryClient.invalidateQueries({ queryKey: [KEY, 'methods'] }),
    });
}

/** Comprobante para imprimir: del propio cliente o, en /admin, de cualquiera. */
export function useInvoice(id: number, scope: 'client' | 'admin') {
    return useQuery({
        queryKey: [KEY, scope, 'invoice', id],
        queryFn: async () => {
            const url = scope === 'admin' ? `/api/admin/invoices/${id}` : `/api/billing/invoices/${id}`;
            return (await api.get<{ data: Invoice }>(url)).data.data;
        },
    });
}

export interface AdminInvoiceFilters {
    page: number;
    status?: string;
    client_id?: number;
}

export function useAdminInvoices(filters: AdminInvoiceFilters) {
    return useQuery({
        queryKey: [KEY, 'admin', filters],
        queryFn: async () => (await api.get<PaginatedResponse<Invoice>>('/api/admin/invoices', { params: filters })).data,
        placeholderData: keepPreviousData,
    });
}

export function useIssueInvoice(clientId: number) {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: async (input: { description: string; subtotal: string }) =>
            (await api.post<{ data: Invoice }>(`/api/admin/clients/${clientId}/invoices`, input)).data.data,
        onSuccess: () => queryClient.invalidateQueries({ queryKey: [KEY, 'admin'] }),
    });
}

export function useRecordPayment() {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: async ({ invoiceId, ...input }: { invoiceId: number; amount: string; method: string; paid_at?: string; reference?: string }) =>
            (await api.post<{ data: PaymentRecord }>(`/api/admin/invoices/${invoiceId}/payments`, input)).data.data,
        onSuccess: () => queryClient.invalidateQueries({ queryKey: [KEY, 'admin'] }),
    });
}

export function useVoidInvoice() {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: async ({ invoiceId, reason }: { invoiceId: number; reason: string }) =>
            (await api.post<{ data: Invoice }>(`/api/admin/invoices/${invoiceId}/void`, { reason })).data.data,
        onSuccess: () => queryClient.invalidateQueries({ queryKey: [KEY, 'admin'] }),
    });
}

/** Notas de crédito: las propias o, en /admin, las de un cliente. */
export function useCreditNotes(scope: 'client' | 'admin', page: number, clientId?: number) {
    return useQuery({
        queryKey: [KEY, scope, 'credit-notes', clientId ?? null, page],
        queryFn: async () => {
            const url = scope === 'admin' ? '/api/admin/credit-notes' : '/api/billing/credit-notes';
            return (await api.get<PaginatedResponse<CreditNote>>(url, { params: { page, client_id: clientId } })).data;
        },
        placeholderData: keepPreviousData,
    });
}

export function useCreditNote(id: number, scope: 'client' | 'admin') {
    return useQuery({
        queryKey: [KEY, scope, 'credit-note', id],
        queryFn: async () => {
            const url = scope === 'admin' ? `/api/admin/credit-notes/${id}` : `/api/billing/credit-notes/${id}`;
            return (await api.get<{ data: CreditNote }>(url)).data.data;
        },
    });
}
