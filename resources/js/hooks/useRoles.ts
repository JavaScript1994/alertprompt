import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { PermissionName, PermissionTreeSection, Role, RoleScope } from '@/types';

const ROLES_QUERY_KEY = 'admin-roles';

export interface RoleFormInput {
    label: string;
    description: string | null;
    permissions: PermissionName[];
}

export function useRoles() {
    return useQuery({
        queryKey: [ROLES_QUERY_KEY],
        queryFn: async () => {
            const { data } = await api.get<{ data: Role[] }>('/api/admin/roles');

            return data.data;
        },
    });
}

export function useRole(id: number | null) {
    return useQuery({
        queryKey: [ROLES_QUERY_KEY, id],
        queryFn: async () => {
            const { data } = await api.get<{ data: Role }>(`/api/admin/roles/${id}`);

            return data.data;
        },
        enabled: id !== null,
    });
}

/** El árbol sale de config/permissions.php y casi nunca cambia. */
export function usePermissionTree() {
    return useQuery({
        queryKey: ['admin-permission-tree'],
        queryFn: async () => {
            const { data } = await api.get<{ data: PermissionTreeSection[] }>('/api/admin/permissions');

            return data.data;
        },
        staleTime: Infinity,
    });
}

export function useCreateRole() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (input: RoleFormInput & { scope: RoleScope }) => {
            const { data } = await api.post<{ data: Role }>('/api/admin/roles', input);

            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: [ROLES_QUERY_KEY] });
        },
    });
}

export function useUpdateRole() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, ...input }: RoleFormInput & { id: number }) => {
            const { data } = await api.put<{ data: Role }>(`/api/admin/roles/${id}`, input);

            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: [ROLES_QUERY_KEY] });
            // Si el dueño editó un rol que él mismo usa, su menú debe reflejarlo.
            queryClient.invalidateQueries({ queryKey: ['auth', 'user'] });
        },
    });
}

export function useDeleteRole() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (id: number) => {
            await api.delete(`/api/admin/roles/${id}`);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: [ROLES_QUERY_KEY] });
        },
    });
}
