import { AxiosError } from 'axios';

/** Iniciales para avatares: "Ana Torres" → "AT". */
export function initials(name?: string): string {
    if (!name) return '·';
    const parts = name.trim().split(/\s+/);
    return ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase();
}

export function formatDateTime(iso: string, dateStyle: 'short' | 'medium' = 'medium'): string {
    return new Date(iso).toLocaleString('es-PE', { dateStyle, timeStyle: 'short' });
}

/** Mensaje de error del backend (422/4xx) o un texto por defecto. */
export function apiErrorMessage(error: unknown, fields: string[], fallback: string): string {
    if (!(error instanceof AxiosError)) return fallback;

    const data = error.response?.data as { errors?: Record<string, string[]>; message?: string } | undefined;
    for (const field of fields) {
        const message = data?.errors?.[field]?.[0];
        if (message) return message;
    }
    return data?.message ?? fallback;
}
