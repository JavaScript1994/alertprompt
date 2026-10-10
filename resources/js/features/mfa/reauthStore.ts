import type { SensitiveAction } from './types';

export interface PendingReauth {
    action: SensitiveAction;
    label: string;
}

interface Waiter {
    resolve: () => void;
    reject: () => void;
}

let pending: PendingReauth | null = null;
let waiters: Waiter[] = [];
const listeners = new Set<() => void>();

const emit = () => listeners.forEach((listener) => listener());

/**
 * Re-autenticación pendiente: el interceptor de axios pide confirmar una
 * acción sensible y espera; ReauthModal la muestra (useSyncExternalStore) y
 * resuelve o cancela. Pedidos simultáneos de la misma acción comparten modal.
 */
export const reauthStore = {
    subscribe(listener: () => void) {
        listeners.add(listener);
        return () => listeners.delete(listener);
    },
    getSnapshot: (): PendingReauth | null => pending,

    request(next: PendingReauth): Promise<void> {
        if (pending && pending.action !== next.action) {
            return Promise.reject(new Error('Ya hay una confirmación en curso.'));
        }
        pending = next;
        emit();
        return new Promise((resolve, reject) => waiters.push({ resolve, reject }));
    },

    settle(confirmed: boolean) {
        const current = waiters;
        pending = null;
        waiters = [];
        emit();
        current.forEach((waiter) => (confirmed ? waiter.resolve() : waiter.reject()));
    },
};
