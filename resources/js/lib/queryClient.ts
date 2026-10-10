import { QueryClient } from '@tanstack/react-query';

/** Único QueryClient de la app (lo usan también los interceptores de axios). */
export const queryClient = new QueryClient();
