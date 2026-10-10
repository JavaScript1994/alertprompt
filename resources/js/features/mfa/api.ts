import { useMutation, useQueryClient } from '@tanstack/react-query';
import { AxiosError } from 'axios';
import { api, ensureCsrfCookie } from '@/lib/api';
import type { User } from '@/types';
import type { MfaErrorBody, MfaErrorCode, MfaSetup, MfaStatus, SensitiveAction } from './types';

export const AUTH_USER_QUERY_KEY = ['auth', 'user'];

interface Envelope<T> {
    data: T;
}

/** `code` del error del flujo de MFA, si lo es. */
export function mfaErrorCode(error: unknown): MfaErrorCode | null {
    if (!(error instanceof AxiosError)) return null;
    const body = error.response?.data as Partial<MfaErrorBody> | undefined;
    return body?.code ?? null;
}

export function mfaErrorBody(error: unknown): MfaErrorBody | null {
    return error instanceof AxiosError && mfaErrorCode(error) ? (error.response?.data as MfaErrorBody) : null;
}

/** Tras un cambio del MFA, el usuario (con su `mfa`) se vuelve a pedir. */
function useRefreshUser() {
    const queryClient = useQueryClient();
    return () => queryClient.invalidateQueries({ queryKey: AUTH_USER_QUERY_KEY });
}

function useSetUser() {
    const queryClient = useQueryClient();
    return (user: User) => queryClient.setQueryData(AUTH_USER_QUERY_KEY, user);
}

// ── Enrolamiento ─────────────────────────────────────────────

export function useStartMfaSetup() {
    return useMutation({
        mutationFn: async () => {
            const { data } = await api.post<Envelope<MfaSetup>>('/api/mfa/setup');
            return data.data;
        },
    });
}

/**
 * No actualiza el usuario en caché: la pantalla primero muestra los códigos
 * de recuperación y aplica el usuario (useApplyUser) cuando se guardaron.
 */
export function useConfirmMfa() {
    return useMutation({
        mutationFn: async (code: string) => {
            const { data } = await api.post<Envelope<{ recovery_codes: string[]; user: User }>>('/api/mfa/confirm', { code });
            return data.data;
        },
    });
}

/** Aplica el usuario devuelto por /mfa/confirm cuando el usuario guardó sus códigos. */
export function useApplyUser() {
    return useSetUser();
}

export function useStartEmailBackupSetup() {
    return useMutation({
        mutationFn: async (email: string) => {
            await api.post('/api/mfa/email-backup/setup', { email });
        },
    });
}

export function useConfirmEmailBackup() {
    const refresh = useRefreshUser();

    return useMutation({
        mutationFn: async (code: string) => {
            const { data } = await api.post<Envelope<MfaStatus>>('/api/mfa/email-backup/confirm', { code });
            return data.data;
        },
        onSuccess: () => refresh(),
    });
}

// ── Segundo paso del login (sin sesión, con challenge_token) ──

export function useVerifyMfa() {
    const setUser = useSetUser();

    return useMutation({
        mutationFn: async (input: { challenge_token: string; code: string }) => {
            await ensureCsrfCookie();
            const { data } = await api.post<Envelope<User>>('/api/mfa/verify', input);
            return data.data;
        },
        onSuccess: setUser,
    });
}

export function useRecoveryLogin() {
    const setUser = useSetUser();

    return useMutation({
        mutationFn: async (input: { challenge_token: string; recovery_code: string }) => {
            await ensureCsrfCookie();
            const { data } = await api.post<Envelope<User>>('/api/mfa/recovery', input);
            return data.data;
        },
        onSuccess: setUser,
    });
}

export function useEmailBackupChallenge() {
    return useMutation({
        mutationFn: async (challengeToken: string) => {
            await ensureCsrfCookie();
            const { data } = await api.post<{ message: string; email: string }>('/api/mfa/email-backup/challenge', {
                challenge_token: challengeToken,
            });
            return data;
        },
    });
}

export function useEmailBackupLogin() {
    const setUser = useSetUser();

    return useMutation({
        mutationFn: async (input: { challenge_token: string; code: string }) => {
            const { data } = await api.post<Envelope<User>>('/api/mfa/email-backup/verify', input);
            return data.data;
        },
        onSuccess: setUser,
    });
}

// ── Seguridad (sesión verificada) ─────────────────────────────

export function useReauth() {
    return useMutation({
        mutationFn: async (input: { action: SensitiveAction; password: string; code: string }) => {
            await api.post('/api/reauth', input);
        },
    });
}

export function useRegenerateRecoveryCodes() {
    const refresh = useRefreshUser();

    return useMutation({
        mutationFn: async () => {
            const { data } = await api.post<Envelope<{ recovery_codes: string[] }>>('/api/mfa/recovery-codes/regenerate');
            return data.data.recovery_codes;
        },
        onSuccess: () => refresh(),
    });
}

export function useDisableMfa() {
    const refresh = useRefreshUser();

    return useMutation({
        mutationFn: async () => {
            await api.delete('/api/mfa');
        },
        onSuccess: () => refresh(),
    });
}

/** Un administrador restablece el MFA de un usuario de su cuenta. */
export function useResetUserMfa() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (userId: number) => {
            await api.post(`/api/users/${userId}/mfa/reset`);
        },
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['tenant-users'] }),
    });
}

/** La administración general restablece el MFA de un usuario de un cliente. */
export function useResetClientUserMfa(clientId: number) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (userId: number) => {
            await api.post(`/api/admin/clients/${clientId}/users/${userId}/mfa/reset`);
        },
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin-clients', clientId, 'users'] }),
    });
}
