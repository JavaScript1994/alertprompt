import { useCallback } from 'react';
import { useAuthUser } from '@/hooks/useAuth';
import type { ModuleKey, TemplateChannel } from '@/types';

const CHANNELS: TemplateChannel[] = ['whatsapp', 'sms', 'email'];

/**
 * Módulos del tenant que se está viendo (el cliente, en modo soporte). Solo
 * para mostrar u ocultar UI: el backend los vuelve a exigir.
 */
export function useHasModule(): (module: ModuleKey) => boolean {
    const { data: user } = useAuthUser();
    const modules = (user?.impersonating ?? user?.tenant)?.modules;

    return useCallback((module: ModuleKey) => modules?.includes(module) ?? false, [modules]);
}

export function useEnabledChannels(): TemplateChannel[] {
    const hasModule = useHasModule();

    return CHANNELS.filter((channel) => hasModule(channel));
}
