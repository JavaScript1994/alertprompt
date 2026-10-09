import { AxiosError } from 'axios';
import type { FieldValues, Path, UseFormSetError } from 'react-hook-form';

/**
 * Pasa los errores 422 de Laravel a los campos del formulario. Devuelve el
 * primer mensaje que no corresponde a ningún campo conocido (o null).
 */
export function applyServerErrors<T extends FieldValues>(
    error: unknown,
    setError: UseFormSetError<T>,
    fields: readonly Path<T>[],
): string | null {
    if (!(error instanceof AxiosError) || error.response?.status !== 422) {
        return 'No se pudo guardar. Intenta de nuevo.';
    }

    const errors = (error.response.data as { errors?: Record<string, string[]> }).errors ?? {};
    let unmatched: string | null = null;

    for (const [key, messages] of Object.entries(errors)) {
        if ((fields as readonly string[]).includes(key)) {
            setError(key as Path<T>, { type: 'server', message: messages[0] });
        } else {
            unmatched ??= messages[0] ?? null;
        }
    }

    return unmatched;
}
