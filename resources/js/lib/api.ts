import axios from 'axios';

export const api = axios.create({
    baseURL: '/',
    withCredentials: true,
    headers: {
        Accept: 'application/json',
    },
});

export async function ensureCsrfCookie(): Promise<void> {
    await api.get('/sanctum/csrf-cookie');
}

declare module 'axios' {
    interface AxiosRequestConfig {
        /** Ya se repitió tras una re-autenticación (evita bucles). */
        reauthRetried?: boolean;
    }
}
