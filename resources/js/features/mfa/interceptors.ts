import { AxiosError } from 'axios';
import { api } from '@/lib/api';
import { queryClient } from '@/lib/queryClient';
import { AUTH_USER_QUERY_KEY } from './api';
import { reauthStore } from './reauthStore';
import type { MfaErrorBody } from './types';

/** El cuerpo de un error con responseType 'blob' (exportes) llega como Blob. */
async function errorBody(error: AxiosError): Promise<Partial<MfaErrorBody> | undefined> {
    const data = error.response?.data;
    if (data instanceof Blob && data.type.includes('json')) {
        try {
            const parsed = JSON.parse(await data.text()) as Partial<MfaErrorBody>;
            if (error.response) error.response.data = parsed;
            return parsed;
        } catch {
            return undefined;
        }
    }
    return data as Partial<MfaErrorBody> | undefined;
}

/**
 * - 403 reauth_required → abre ReauthModal y, si se confirma, repite la
 *   petición original una vez.
 * - 403 mfa_enrollment_required / mfa_challenge_required → refresca el
 *   usuario; ProtectedRoute decide (enrolar o volver al login).
 */
export function installMfaInterceptors(): void {
    api.interceptors.response.use(undefined, async (error: unknown) => {
        if (!(error instanceof AxiosError) || error.response?.status !== 403 || !error.config) {
            throw error;
        }

        const body = await errorBody(error);

        if (body?.code === 'reauth_required' && body.action && !error.config.reauthRetried) {
            try {
                await reauthStore.request({ action: body.action, label: body.action_label ?? 'Acción sensible' });
            } catch {
                throw error;
            }
            return api.request({ ...error.config, reauthRetried: true });
        }

        if (body?.code === 'mfa_enrollment_required' || body?.code === 'mfa_challenge_required') {
            await queryClient.invalidateQueries({ queryKey: AUTH_USER_QUERY_KEY });
        }

        throw error;
    });
}
