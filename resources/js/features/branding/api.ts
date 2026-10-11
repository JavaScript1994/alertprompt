import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { BrandingSettings, PublicBranding } from './types';

interface Envelope<T> {
    data: T;
}

const SETTINGS_KEY = ['branding-settings'];

/** Marca del login de este subdominio. No cambia durante la visita. */
export function usePublicBranding() {
    return useQuery({
        queryKey: ['public-branding'],
        queryFn: async () => {
            const { data } = await api.get<Envelope<PublicBranding>>('/api/branding');
            return data.data;
        },
        staleTime: Infinity,
        retry: false,
    });
}

export function useBrandingSettings() {
    return useQuery({
        queryKey: SETTINGS_KEY,
        queryFn: async () => {
            const { data } = await api.get<Envelope<BrandingSettings>>('/api/settings/branding');
            return data.data;
        },
    });
}

function useSetSettings() {
    const queryClient = useQueryClient();
    return (settings: BrandingSettings) => queryClient.setQueryData(SETTINGS_KEY, settings);
}

export function useUpdateBranding() {
    const set = useSetSettings();

    return useMutation({
        mutationFn: async (input: { brand_color: string | null; brand_title: string | null }) => {
            const { data } = await api.put<Envelope<BrandingSettings>>('/api/settings/branding', input);
            return data.data;
        },
        onSuccess: set,
    });
}

export function useUploadLogo() {
    const set = useSetSettings();

    return useMutation({
        mutationFn: async (file: File) => {
            const form = new FormData();
            form.append('logo', file);
            const { data } = await api.post<Envelope<BrandingSettings>>('/api/settings/branding/logo', form);
            return data.data;
        },
        onSuccess: set,
    });
}

export function useDeleteLogo() {
    const set = useSetSettings();

    return useMutation({
        mutationFn: async () => {
            const { data } = await api.delete<Envelope<BrandingSettings>>('/api/settings/branding/logo');
            return data.data;
        },
        onSuccess: set,
    });
}
