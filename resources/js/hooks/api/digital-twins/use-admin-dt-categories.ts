import {
    useMutation,
    useQuery,
    useQueryClient,
    type UseMutationOptions,
    type UseMutationResult,
    type UseQueryOptions,
    type UseQueryResult,
} from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import {
    AdminDigitalTwinApi,
    type AdminDigitalTwinCategoryNode,
    type AdminDigitalTwinCategoryPayload,
    type AdminDigitalTwinCategoryResponse,
} from '@/sdk';

export type UseAdminDigitalTwinCategoriesOptions = Pick<
    UseQueryOptions<AdminDigitalTwinCategoryNode[]>,
    'enabled' | 'staleTime' | 'gcTime' | 'refetchOnWindowFocus'
>;

export type UseAdminDigitalTwinCategoriesResult = UseQueryResult<
    AdminDigitalTwinCategoryNode[]
> & {
    categories: AdminDigitalTwinCategoryNode[];
};

export function useAdminDigitalTwinCategories(
    options: UseAdminDigitalTwinCategoriesOptions = {},
): UseAdminDigitalTwinCategoriesResult {
    const api = useSdkClient(AdminDigitalTwinApi);

    const query = useQuery<AdminDigitalTwinCategoryNode[]>({
        queryKey: queryKeys.digitalTwins.adminCategories(),
        queryFn: async () => {
            const response = await api.listCategories({ tree: true });
            return response.data?.items ?? [];
        },
        enabled: options.enabled ?? true,
        staleTime: options.staleTime ?? 5 * 60_000,
        gcTime: options.gcTime,
        refetchOnWindowFocus: options.refetchOnWindowFocus ?? false,
    });

    return {
        ...query,
        categories: query.data ?? [],
    } satisfies UseAdminDigitalTwinCategoriesResult;
}

export type UseCreateAdminDigitalTwinCategoryResult = UseMutationResult<
    AdminDigitalTwinCategoryResponse,
    unknown,
    AdminDigitalTwinCategoryPayload
>;

export function useCreateAdminDigitalTwinCategory(
    options: UseMutationOptions<
        AdminDigitalTwinCategoryResponse,
        unknown,
        AdminDigitalTwinCategoryPayload
    > = {},
): UseCreateAdminDigitalTwinCategoryResult {
    const api = useSdkClient(AdminDigitalTwinApi);
    const queryClient = useQueryClient();

    return useMutation({
        mutationKey: ['digital-twins', 'admin', 'categories', 'create'],
        mutationFn: async (payload) => api.createCategory(payload),
        ...options,
        onSuccess: (data, variables, onMutateResult, context) => {
            queryClient.invalidateQueries({
                queryKey: queryKeys.digitalTwins.adminCategories(),
            });
            options.onSuccess?.(data, variables, onMutateResult, context);
        },
    });
}

export interface UseUpdateAdminDigitalTwinCategoryVariables extends AdminDigitalTwinCategoryPayload {
    categoryId: number | string;
}

export type UseUpdateAdminDigitalTwinCategoryResult = UseMutationResult<
    AdminDigitalTwinCategoryResponse,
    unknown,
    UseUpdateAdminDigitalTwinCategoryVariables
>;

export function useUpdateAdminDigitalTwinCategory(
    options: UseMutationOptions<
        AdminDigitalTwinCategoryResponse,
        unknown,
        UseUpdateAdminDigitalTwinCategoryVariables
    > = {},
): UseUpdateAdminDigitalTwinCategoryResult {
    const api = useSdkClient(AdminDigitalTwinApi);
    const queryClient = useQueryClient();

    return useMutation({
        mutationKey: ['digital-twins', 'admin', 'categories', 'update'],
        mutationFn: async ({ categoryId, ...payload }) =>
            api.updateCategory(categoryId, payload),
        ...options,
        onSuccess: (data, variables, onMutateResult, context) => {
            queryClient.invalidateQueries({
                queryKey: queryKeys.digitalTwins.adminCategories(),
            });
            options.onSuccess?.(data, variables, onMutateResult, context);
        },
    });
}

export interface UseDeleteAdminDigitalTwinCategoryVariables {
    categoryId: number | string;
}

export type UseDeleteAdminDigitalTwinCategoryResult = UseMutationResult<
    AdminDigitalTwinCategoryResponse,
    unknown,
    UseDeleteAdminDigitalTwinCategoryVariables
>;

export function useDeleteAdminDigitalTwinCategory(
    options: UseMutationOptions<
        AdminDigitalTwinCategoryResponse,
        unknown,
        UseDeleteAdminDigitalTwinCategoryVariables
    > = {},
): UseDeleteAdminDigitalTwinCategoryResult {
    const api = useSdkClient(AdminDigitalTwinApi);
    const queryClient = useQueryClient();

    return useMutation({
        mutationKey: ['digital-twins', 'admin', 'categories', 'delete'],
        mutationFn: async ({ categoryId }) => api.deleteCategory(categoryId),
        ...options,
        onSuccess: (data, variables, onMutateResult, context) => {
            queryClient.invalidateQueries({
                queryKey: queryKeys.digitalTwins.adminCategories(),
            });
            options.onSuccess?.(data, variables, onMutateResult, context);
        },
    });
}
