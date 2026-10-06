import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { Consent, TemplateChannel } from '@/types';

const CONSENTS_QUERY_KEY = 'consents';
const CONTACTS_QUERY_KEY = 'contacts';

export function useConsents(contactId: number | null) {
    return useQuery({
        queryKey: [CONSENTS_QUERY_KEY, contactId],
        queryFn: async () => {
            const { data } = await api.get<{ data: Consent[] }>(`/api/contacts/${contactId}/consents`);

            return data.data;
        },
        enabled: contactId !== null,
    });
}

export function useGrantConsent(contactId: number | null) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (input: { channel: TemplateChannel; source: string; evidence_text: string }) => {
            const { data } = await api.post<{ data: Consent }>(`/api/contacts/${contactId}/consents`, input);

            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: [CONSENTS_QUERY_KEY, contactId] });
            // El estado de consentimiento no se ve en el listado de Contactos
            // hoy, pero campañas/otras vistas sí dependen de datos de contacto.
            queryClient.invalidateQueries({ queryKey: [CONTACTS_QUERY_KEY] });
        },
    });
}

export function useRevokeConsent(contactId: number | null) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (consentId: number) => {
            const { data } = await api.patch<{ data: Consent }>(
                `/api/contacts/${contactId}/consents/${consentId}/revoke`,
            );

            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: [CONSENTS_QUERY_KEY, contactId] });
            queryClient.invalidateQueries({ queryKey: [CONTACTS_QUERY_KEY] });
        },
    });
}
