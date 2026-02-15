import { zodResolver } from '@hookform/resolvers/zod';
import { Loader2, PlusCircle, RefreshCcw, Save, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState, type ChangeEvent } from 'react';
import { Helmet } from 'react-helmet-async';
import { Controller, useForm, useWatch } from 'react-hook-form';
import { z } from 'zod';

import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { publishToast } from '@/components/ui/use-toast';
import { useAuth } from '@/contexts/auth-context';
import {
    useAdminDigitalTwinCategories,
    useCreateAdminDigitalTwinCategory,
    useDeleteAdminDigitalTwinCategory,
    useUpdateAdminDigitalTwinCategory,
} from '@/hooks/api/digital-twins';
import { AccessDeniedPage } from '@/pages/errors/access-denied-page';
import type { AdminDigitalTwinCategoryNode } from '@/sdk';
import {
    flattenDigitalTwinCategories,
    resolveDigitalTwinErrorMessage,
} from './digital-twin-form-utils';

const categoryFormSchema = z.object({
    name: z
        .string()
        .min(2, 'Name is required')
        .max(120, 'Keep the name under 120 characters'),
    slug: z
        .string()
        .min(2, 'Slug is required')
        .max(120, 'Slug must be 120 characters or fewer')
        .regex(
            /^[a-z0-9-]+$/,
            'Only lowercase letters, numbers, and hyphens are allowed',
        ),
    description: z
        .string()
        .max(500, 'Description is too long')
        .optional()
        .nullable(),
    parentId: z.string().optional().nullable(),
    isActive: z.boolean().default(true),
});

type CategoryFormValues = z.infer<typeof categoryFormSchema>;

const CATEGORY_FORM_DEFAULTS: CategoryFormValues = {
    name: '',
    slug: '',
    description: '',
    parentId: null,
    isActive: true,
};

const NO_PARENT_OPTION_VALUE = '__no_parent__';

export function AdminDigitalTwinCategoriesPage() {
    const { isAdmin } = useAuth();
    const { categories, isLoading, isFetching, error, refetch } =
        useAdminDigitalTwinCategories();
    const createCategory = useCreateAdminDigitalTwinCategory();
    const updateCategory = useUpdateAdminDigitalTwinCategory();
    const deleteCategory = useDeleteAdminDigitalTwinCategory();

    const [selectedCategoryId, setSelectedCategoryId] = useState<number | null>(
        null,
    );
    const [pendingDeleteCategory, setPendingDeleteCategory] =
        useState<AdminDigitalTwinCategoryNode | null>(null);
    const [slugIsManual, setSlugIsManual] = useState(false);

    const form = useForm<CategoryFormValues>({
        resolver: zodResolver(categoryFormSchema),
        defaultValues: CATEGORY_FORM_DEFAULTS,
        mode: 'onChange',
    });

    const nameValue = useWatch({ control: form.control, name: 'name' });
    const slugValue = useWatch({ control: form.control, name: 'slug' });

    const selectedCategory = useMemo(
        () => findCategoryById(categories, selectedCategoryId),
        [categories, selectedCategoryId],
    );

    const descendantIds = useMemo(() => {
        if (!selectedCategory) {
            return new Set<number>();
        }

        return new Set<number>([
            selectedCategory.id,
            ...collectDescendantIds(selectedCategory),
        ]);
    }, [selectedCategory]);

    const parentOptions = useMemo(() => {
        const flattened = flattenDigitalTwinCategories(categories);
        return flattened.filter((option) => !descendantIds.has(option.value));
    }, [categories, descendantIds]);

    const isSaving = createCategory.isPending || updateCategory.isPending;
    const isRefreshing = isFetching && !isLoading;

    const canSubmit = form.formState.isValid && !isSaving;

    const resetToCreateMode = () => {
        setSelectedCategoryId(null);
        setSlugIsManual(false);
        form.reset(CATEGORY_FORM_DEFAULTS, {
            keepErrors: false,
            keepDirty: false,
        });
    };

    const handleSelectCategory = (category: AdminDigitalTwinCategoryNode) => {
        setSelectedCategoryId(category.id);
        setSlugIsManual(true);
        form.reset(
            {
                name: category.name ?? '',
                slug: category.slug ?? '',
                description: category.description ?? '',
                parentId:
                    category.parent_id != null
                        ? String(category.parent_id)
                        : null,
                isActive: Boolean(category.is_active),
            },
            { keepErrors: false, keepDirty: false },
        );
    };

    useEffect(() => {
        if (slugIsManual || selectedCategoryId) {
            return;
        }

        const generatedSlug = toSlug(nameValue ?? '');
        if (generatedSlug !== slugValue) {
            form.setValue('slug', generatedSlug, { shouldValidate: true });
        }
    }, [form, nameValue, selectedCategoryId, slugIsManual, slugValue]);

    const handleSlugInput = (event: ChangeEvent<HTMLInputElement>) => {
        setSlugIsManual(true);
        form.setValue('slug', event.currentTarget.value, {
            shouldValidate: true,
            shouldDirty: true,
        });
    };

    const handleSubmit = form.handleSubmit(async (values) => {
        const payload = {
            name: values.name.trim(),
            slug: toSlug(values.slug),
            description: values.description?.trim() || undefined,
            parent_id: values.parentId ? Number(values.parentId) : undefined,
            is_active: values.isActive,
        };

        try {
            if (selectedCategory) {
                await updateCategory.mutateAsync({
                    categoryId: selectedCategory.id,
                    ...payload,
                });
                publishToast({
                    title: 'Category updated',
                    description: `${values.name} saved successfully.`,
                    variant: 'success',
                });
            } else {
                await createCategory.mutateAsync(payload);
                publishToast({
                    title: 'Category created',
                    description: `${values.name} is ready to use.`,
                    variant: 'success',
                });
                resetToCreateMode();
            }
        } catch (err) {
            publishToast({
                title: 'Unable to save category',
                description: resolveDigitalTwinErrorMessage(err),
                variant: 'destructive',
            });
        }
    });

    const handleRequestDelete = (category: AdminDigitalTwinCategoryNode) => {
        setPendingDeleteCategory(category);
    };

    const handleConfirmDelete = async () => {
        if (!pendingDeleteCategory) {
            return;
        }

        try {
            await deleteCategory.mutateAsync({
                categoryId: pendingDeleteCategory.id,
            });
            publishToast({
                title: 'Category deleted',
                description: `${pendingDeleteCategory.name} has been removed.`,
            });
            if (pendingDeleteCategory.id === selectedCategoryId) {
                resetToCreateMode();
            }
        } catch (err) {
            publishToast({
                title: 'Unable to delete category',
                description: resolveDigitalTwinErrorMessage(err),
                variant: 'destructive',
            });
        } finally {
            setPendingDeleteCategory(null);
        }
    };

    const deletionBlocked = Boolean(pendingDeleteCategory?.children?.length);

    if (!isAdmin) {
        return <AccessDeniedPage />;
    }

    return (
        <section className="space-y-6">
            <Helmet>
                <title>Admin · Digital twin categories</title>
            </Helmet>

            <Heading
                title="Digital twin categories"
                description="Organise digital twins in a tree so buyers can filter and discovery stays clean."
                action={
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => refetch()}
                            disabled={isRefreshing}
                        >
                            <RefreshCcw className="mr-2 h-4 w-4" /> Refresh
                        </Button>
                        <Button size="sm" onClick={resetToCreateMode}>
                            <PlusCircle className="mr-2 h-4 w-4" /> New category
                        </Button>
                    </div>
                }
            />

            {error ? (
                <Alert variant="destructive">
                    <AlertTitle>Unable to load categories</AlertTitle>
                    <AlertDescription>
                        {resolveDigitalTwinErrorMessage(error)}
                    </AlertDescription>
                </Alert>
            ) : null}

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
                <Card className="border-border/70">
                    <CardHeader>
                        <CardTitle>Category tree</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {isLoading ? (
                            <Skeleton className="h-48 w-full" />
                        ) : categories.length === 0 ? (
                            <Alert>
                                <AlertTitle>No categories</AlertTitle>
                                <AlertDescription>
                                    Create your first category to group reusable
                                    assets.
                                </AlertDescription>
                            </Alert>
                        ) : (
                            <CategoryTree
                                nodes={categories}
                                selectedId={selectedCategoryId}
                                onSelect={handleSelectCategory}
                                onDelete={handleRequestDelete}
                            />
                        )}
                    </CardContent>
                </Card>

                <Card className="border-border/70">
                    <CardHeader>
                        <CardTitle>
                            {selectedCategory
                                ? 'Edit category'
                                : 'New category'}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <form className="space-y-4" onSubmit={handleSubmit}>
                            <div className="space-y-2">
                                <Label htmlFor="category-name">Name</Label>
                                <Input
                                    id="category-name"
                                    placeholder="e.g. Batteries"
                                    {...form.register('name')}
                                />
                                {form.formState.errors.name ? (
                                    <p className="text-xs text-destructive">
                                        {form.formState.errors.name.message}
                                    </p>
                                ) : null}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="category-slug">Slug</Label>
                                <Input
                                    id="category-slug"
                                    value={slugValue ?? ''}
                                    onChange={handleSlugInput}
                                    placeholder="batteries"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Used in URLs and APIs. Lowercase letters,
                                    numbers, and hyphens only.
                                </p>
                                {form.formState.errors.slug ? (
                                    <p className="text-xs text-destructive">
                                        {form.formState.errors.slug.message}
                                    </p>
                                ) : null}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="category-parent">
                                    Parent category
                                </Label>
                                <Controller
                                    name="parentId"
                                    control={form.control}
                                    render={({ field }) => (
                                        <Select
                                            value={
                                                field.value ??
                                                NO_PARENT_OPTION_VALUE
                                            }
                                            onValueChange={(value) =>
                                                field.onChange(
                                                    value ===
                                                        NO_PARENT_OPTION_VALUE
                                                        ? null
                                                        : value,
                                                )
                                            }
                                        >
                                            <SelectTrigger id="category-parent">
                                                <SelectValue placeholder="No parent" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    value={
                                                        NO_PARENT_OPTION_VALUE
                                                    }
                                                >
                                                    No parent
                                                </SelectItem>
                                                {parentOptions.map((option) => (
                                                    <SelectItem
                                                        key={option.value}
                                                        value={String(
                                                            option.value,
                                                        )}
                                                    >
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    )}
                                />
                                {form.formState.errors.parentId ? (
                                    <p className="text-xs text-destructive">
                                        {form.formState.errors.parentId.message}
                                    </p>
                                ) : null}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="category-description">
                                    Description
                                </Label>
                                <Textarea
                                    id="category-description"
                                    rows={4}
                                    placeholder="Optional summary shown in admin tools"
                                    {...form.register('description')}
                                />
                                {form.formState.errors.description ? (
                                    <p className="text-xs text-destructive">
                                        {
                                            form.formState.errors.description
                                                .message
                                        }
                                    </p>
                                ) : null}
                            </div>
                            <div className="flex items-center gap-2">
                                <Controller
                                    name="isActive"
                                    control={form.control}
                                    render={({ field }) => (
                                        <Checkbox
                                            checked={field.value}
                                            onCheckedChange={(checked) =>
                                                field.onChange(Boolean(checked))
                                            }
                                        />
                                    )}
                                />
                                <span className="text-sm text-muted-foreground">
                                    Category is active
                                </span>
                            </div>
                            <div className="flex flex-wrap items-center justify-end gap-2">
                                {selectedCategory ? (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={resetToCreateMode}
                                    >
                                        Cancel
                                    </Button>
                                ) : null}
                                <Button type="submit" disabled={!canSubmit}>
                                    {isSaving ? (
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    ) : (
                                        <Save className="mr-2 h-4 w-4" />
                                    )}
                                    {selectedCategory
                                        ? 'Save changes'
                                        : 'Create category'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>

            <ConfirmDialog
                open={Boolean(pendingDeleteCategory)}
                onOpenChange={(open) =>
                    setPendingDeleteCategory(
                        open ? pendingDeleteCategory : null,
                    )
                }
                title={
                    deletionBlocked
                        ? 'Cannot delete category'
                        : 'Delete category?'
                }
                description={
                    deletionBlocked
                        ? 'Delete or reassign any child categories before removing this parent.'
                        : `Deleting ${pendingDeleteCategory?.name ?? 'this category'} removes it from the library.`
                }
                confirmLabel="Delete"
                confirmVariant="destructive"
                isProcessing={deleteCategory.isPending}
                disableConfirm={deletionBlocked}
                onConfirm={() => {
                    if (deletionBlocked) {
                        return;
                    }

                    void handleConfirmDelete();
                }}
            />
        </section>
    );
}

function CategoryTree({
    nodes,
    selectedId,
    onSelect,
    onDelete,
}: {
    nodes: AdminDigitalTwinCategoryNode[];
    selectedId: number | null;
    onSelect: (node: AdminDigitalTwinCategoryNode) => void;
    onDelete: (node: AdminDigitalTwinCategoryNode) => void;
}) {
    return (
        <div className="space-y-2">
            {nodes.map((node) => (
                <CategoryTreeNode
                    key={node.id}
                    node={node}
                    level={0}
                    selectedId={selectedId}
                    onSelect={onSelect}
                    onDelete={onDelete}
                />
            ))}
        </div>
    );
}

function CategoryTreeNode({
    node,
    level,
    selectedId,
    onSelect,
    onDelete,
}: {
    node: AdminDigitalTwinCategoryNode;
    level: number;
    selectedId: number | null;
    onSelect: (node: AdminDigitalTwinCategoryNode) => void;
    onDelete: (node: AdminDigitalTwinCategoryNode) => void;
}) {
    const isSelected = selectedId === node.id;

    return (
        <div className="space-y-2">
            <div
                className="flex items-center justify-between gap-2"
                style={{ paddingLeft: level * 16 }}
            >
                <button
                    type="button"
                    onClick={() => onSelect(node)}
                    className={`flex flex-1 flex-col items-start rounded-md border px-3 py-2 text-left transition hover:border-foreground/40 ${
                        isSelected
                            ? 'border-primary bg-primary/5'
                            : 'border-border'
                    }`}
                >
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="font-medium text-foreground">
                            {node.name}
                        </span>
                        {!node.is_active ? (
                            <Badge variant="outline">Inactive</Badge>
                        ) : null}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        /{node.slug}
                    </p>
                </button>
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => onDelete(node)}
                    aria-label={`Delete ${node.name}`}
                >
                    <Trash2 className="h-4 w-4 text-destructive" />
                </Button>
            </div>
            {node.children && node.children.length > 0 ? (
                <div className="space-y-2">
                    {node.children.map((child) => (
                        <CategoryTreeNode
                            key={child.id}
                            node={child}
                            level={level + 1}
                            selectedId={selectedId}
                            onSelect={onSelect}
                            onDelete={onDelete}
                        />
                    ))}
                </div>
            ) : null}
        </div>
    );
}

function findCategoryById(
    nodes: AdminDigitalTwinCategoryNode[],
    id: number | null,
): AdminDigitalTwinCategoryNode | null {
    if (!id) {
        return null;
    }

    for (const node of nodes) {
        if (node.id === id) {
            return node;
        }

        if (node.children && node.children.length > 0) {
            const match = findCategoryById(node.children, id);
            if (match) {
                return match;
            }
        }
    }

    return null;
}

function collectDescendantIds(node: AdminDigitalTwinCategoryNode): number[] {
    if (!node.children || node.children.length === 0) {
        return [];
    }

    return node.children.flatMap((child) => [
        child.id,
        ...collectDescendantIds(child),
    ]);
}

function toSlug(value: string): string {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .replace(/-{2,}/g, '-')
        .slice(0, 120);
}
