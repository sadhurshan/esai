import { zodResolver } from '@hookform/resolvers/zod';
import { formatDistanceToNow } from 'date-fns';
import {
    Archive,
    Download,
    Loader2,
    PlusCircle,
    RefreshCcw,
    Save,
    Trash2,
    Upload,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { Helmet } from 'react-helmet-async';
import { Controller, useFieldArray, useForm, useWatch } from 'react-hook-form';
import { useNavigate, useParams } from 'react-router-dom';

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
    useAdminDigitalTwin,
    useAdminDigitalTwinAuditEvents,
    useAdminDigitalTwinCategories,
    useArchiveAdminDigitalTwin,
    useDeleteAdminDigitalTwin,
    useDeleteAdminDigitalTwinAsset,
    usePublishAdminDigitalTwin,
    useUpdateAdminDigitalTwin,
    useUploadAdminDigitalTwinAsset,
} from '@/hooks/api/digital-twins';
import { AccessDeniedPage } from '@/pages/errors/access-denied-page';
import type {
    AdminDigitalTwinAsset,
    AdminDigitalTwinAuditEvent,
    AdminDigitalTwinListItem,
} from '@/sdk';
import type { AdminDigitalTwinFormValues } from './digital-twin-form-utils';
import {
    ADMIN_DIGITAL_TWIN_FORM_DEFAULTS,
    adminDigitalTwinFormSchema,
    flattenDigitalTwinCategories,
    mapDigitalTwinToFormValues,
    resolveDigitalTwinErrorMessage,
} from './digital-twin-form-utils';

const ASSET_TYPE_OPTIONS = [
    { value: 'CAD', label: 'CAD' },
    { value: 'STEP', label: 'STEP' },
    { value: 'STL', label: 'STL' },
    { value: 'PDF', label: 'PDF' },
    { value: 'IMAGE', label: 'Image' },
    { value: 'DATA', label: 'Data' },
    { value: 'OTHER', label: 'Other' },
] as const;

type AssetTypeValue = (typeof ASSET_TYPE_OPTIONS)[number]['value'];

export function AdminDigitalTwinDetailPage() {
    const { isAdmin } = useAuth();
    const navigate = useNavigate();
    const params = useParams<{ id: string }>();
    const digitalTwinId = params.id;

    const { digitalTwin, isLoading, isFetching, error, refetch } =
        useAdminDigitalTwin(digitalTwinId);
    const categoriesQuery = useAdminDigitalTwinCategories();
    const categoryOptions = useMemo(
        () => flattenDigitalTwinCategories(categoriesQuery.categories),
        [categoriesQuery.categories],
    );
    const [tagDraft, setTagDraft] = useState('');
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [assetPendingDelete, setAssetPendingDelete] =
        useState<AdminDigitalTwinAsset | null>(null);
    const [assetType, setAssetType] = useState<AssetTypeValue>('CAD');
    const [assetIsPrimary, setAssetIsPrimary] = useState(false);
    const [assetFile, setAssetFile] = useState<File | null>(null);
    const fileInputRef = useRef<HTMLInputElement | null>(null);

    const form = useForm<AdminDigitalTwinFormValues>({
        resolver: zodResolver(adminDigitalTwinFormSchema),
        defaultValues: ADMIN_DIGITAL_TWIN_FORM_DEFAULTS,
    });
    const {
        fields: specFields,
        append,
        remove,
    } = useFieldArray({ control: form.control, name: 'specs' });
    const tags = useWatch({ control: form.control, name: 'tags' });
    const specErrors = Array.isArray(form.formState.errors.specs)
        ? form.formState.errors.specs
        : [];
    const currentCategoryId = digitalTwin?.category?.id ?? null;
    const currentCategoryName = digitalTwin?.category?.name ?? null;
    const detailCategoryOptions = useMemo(() => {
        if (
            !currentCategoryId ||
            categoryOptions.some((option) => option.value === currentCategoryId)
        ) {
            return categoryOptions;
        }

        return [
            {
                value: currentCategoryId,
                label: currentCategoryName ?? 'Current category',
            },
            ...categoryOptions,
        ];
    }, [categoryOptions, currentCategoryId, currentCategoryName]);

    useEffect(() => {
        if (digitalTwin) {
            form.reset(mapDigitalTwinToFormValues(digitalTwin));
        }
    }, [digitalTwin, form]);

    const updateTwin = useUpdateAdminDigitalTwin();
    const publishTwin = usePublishAdminDigitalTwin();
    const archiveTwin = useArchiveAdminDigitalTwin();
    const deleteTwin = useDeleteAdminDigitalTwin();
    const uploadAsset = useUploadAdminDigitalTwinAsset();
    const deleteAsset = useDeleteAdminDigitalTwinAsset();
    const auditEventsQuery = useAdminDigitalTwinAuditEvents(digitalTwin?.id);
    const auditEvents = auditEventsQuery.events;
    const categoryError = categoriesQuery.error;

    const isRefreshing = isFetching && !isLoading;

    if (!isAdmin) {
        return <AccessDeniedPage />;
    }

    if (isLoading && !digitalTwin) {
        return <AdminDigitalTwinDetailSkeleton />;
    }

    if (error && !digitalTwin) {
        return (
            <section className="space-y-6">
                <Helmet>
                    <title>Admin · Digital twin</title>
                </Helmet>
                <Heading
                    title="Digital twin"
                    description="Manage specs and assets for published content."
                />
                <Alert variant="destructive">
                    <AlertTitle>Unable to load digital twin</AlertTitle>
                    <AlertDescription>
                        {resolveDigitalTwinErrorMessage(error)}
                    </AlertDescription>
                </Alert>
            </section>
        );
    }

    if (!digitalTwin) {
        return (
            <section className="space-y-6">
                <Helmet>
                    <title>Admin · Digital twin</title>
                </Helmet>
                <Heading
                    title="Digital twin"
                    description="Manage specs and assets for published content."
                />
                <Alert>
                    <AlertTitle>Digital twin not found</AlertTitle>
                    <AlertDescription>
                        The requested digital twin was not found or may have
                        been deleted.
                    </AlertDescription>
                </Alert>
                <Button
                    onClick={() => navigate('/app/admin/digital-twins')}
                    variant="secondary"
                >
                    Back to list
                </Button>
            </section>
        );
    }

    const canSave = form.formState.isDirty && !updateTwin.isPending;

    const handleAddTag = () => {
        const cleanValue = tagDraft.trim();
        if (!cleanValue) {
            return;
        }

        const normalized = cleanValue.toLowerCase();
        const exists = tags.some((tag) => tag.toLowerCase() === normalized);
        if (exists) {
            publishToast({
                title: 'Tag already added',
                description: `${cleanValue} is already in the list.`,
            });
            return;
        }

        if (tags.length >= 12) {
            publishToast({
                title: 'Tag limit reached',
                description: 'You can add up to 12 tags per digital twin.',
            });
            return;
        }

        form.setValue('tags', [...tags, cleanValue], {
            shouldDirty: true,
            shouldValidate: true,
        });
        setTagDraft('');
    };

    const handleRemoveTag = (tagToRemove: string) => {
        const nextTags = tags.filter((tag) => tag !== tagToRemove);
        form.setValue('tags', nextTags, {
            shouldDirty: true,
            shouldValidate: true,
        });
    };

    const handleSubmit = form.handleSubmit(async (values) => {
        try {
            await updateTwin.mutateAsync({
                digitalTwinId: digitalTwin.id,
                category_id: Number(values.categoryId),
                code: values.code?.trim() || undefined,
                title: values.title.trim(),
                summary: values.summary?.trim() || undefined,
                version: values.version.trim(),
                revision_notes: values.revisionNotes?.trim() || undefined,
                tags: values.tags,
                specs: values.specs.map((spec, index) => ({
                    name: spec.name.trim(),
                    value: spec.value.trim(),
                    uom: spec.uom?.trim() || undefined,
                    sort_order: index + 1,
                })),
                visibility: digitalTwin.visibility ?? 'public',
            });

            publishToast({
                title: 'Digital twin updated',
                description: 'Metadata and specs saved successfully.',
                variant: 'success',
            });
        } catch (err) {
            publishToast({
                title: 'Unable to save changes',
                description: resolveDigitalTwinErrorMessage(err),
                variant: 'destructive',
            });
        }
    });

    const handlePublish = async () => {
        try {
            await publishTwin.mutateAsync({ digitalTwinId: digitalTwin.id });
            publishToast({
                title: 'Digital twin published',
                description: `${digitalTwin.title} is now available in the buyer library.`,
                variant: 'success',
            });
        } catch (err) {
            publishToast({
                title: 'Unable to publish digital twin',
                description: resolveDigitalTwinErrorMessage(err),
                variant: 'destructive',
            });
        }
    };

    const handleArchive = async () => {
        try {
            await archiveTwin.mutateAsync({ digitalTwinId: digitalTwin.id });
            publishToast({
                title: 'Digital twin archived',
                description: `${digitalTwin.title} is hidden from buyers.`,
            });
        } catch (err) {
            publishToast({
                title: 'Unable to archive digital twin',
                description: resolveDigitalTwinErrorMessage(err),
                variant: 'destructive',
            });
        }
    };

    const handleDeleteTwin = async () => {
        try {
            await deleteTwin.mutateAsync({ digitalTwinId: digitalTwin.id });
            publishToast({
                title: 'Digital twin deleted',
                description: `${digitalTwin.title} has been removed.`,
            });
            navigate('/app/admin/digital-twins');
        } catch (err) {
            publishToast({
                title: 'Unable to delete digital twin',
                description: resolveDigitalTwinErrorMessage(err),
                variant: 'destructive',
            });
        } finally {
            setDeleteDialogOpen(false);
        }
    };

    const handleAssetFormSubmit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!assetFile) {
            publishToast({
                title: 'Select a file',
                description: 'Choose a file to upload before submitting.',
            });
            return;
        }

        try {
            await uploadAsset.mutateAsync({
                digitalTwinId: digitalTwin.id,
                file: assetFile,
                type: assetType,
                is_primary: assetIsPrimary,
            });
            publishToast({
                title: 'Asset uploaded',
                description: `${assetFile.name} added to ${digitalTwin.title}.`,
                variant: 'success',
            });
            setAssetFile(null);
            setAssetIsPrimary(false);
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
        } catch (err) {
            publishToast({
                title: 'Unable to upload asset',
                description: resolveDigitalTwinErrorMessage(err),
                variant: 'destructive',
            });
        }
    };

    const handleConfirmAssetDelete = async () => {
        if (!assetPendingDelete) {
            return;
        }

        try {
            await deleteAsset.mutateAsync({
                digitalTwinId: digitalTwin.id,
                assetId: assetPendingDelete.id,
            });
            publishToast({
                title: 'Asset deleted',
                description: `${assetPendingDelete.filename} has been removed.`,
            });
        } catch (err) {
            publishToast({
                title: 'Unable to delete asset',
                description: resolveDigitalTwinErrorMessage(err),
                variant: 'destructive',
            });
        } finally {
            setAssetPendingDelete(null);
        }
    };

    const isPublished = digitalTwin.status === 'published';
    const isArchived = digitalTwin.status === 'archived';

    return (
        <section className="space-y-6">
            <Helmet>
                <title>{`${digitalTwin.title} · Admin Digital Twin`}</title>
            </Helmet>

            <Heading
                title={digitalTwin.title ?? 'Digital twin'}
                description="Update metadata, specs, and assets before publishing to the buyer library."
                action={
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => navigate('/app/admin/digital-twins')}
                        >
                            Back to list
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => refetch()}
                            disabled={isRefreshing}
                        >
                            <RefreshCcw className="mr-2 h-4 w-4" /> Refresh
                        </Button>
                    </div>
                }
            />

            {categoryError ? (
                <Alert variant="destructive">
                    <AlertTitle>Unable to load categories</AlertTitle>
                    <AlertDescription>
                        {resolveDigitalTwinErrorMessage(categoryError)}
                    </AlertDescription>
                </Alert>
            ) : null}

            {!categoriesQuery.isLoading && categoryOptions.length === 0 ? (
                <Alert>
                    <AlertTitle>No categories available</AlertTitle>
                    <AlertDescription>
                        Create at least one digital twin category before
                        publishing content. Manage categories from the admin
                        dashboard.
                    </AlertDescription>
                </Alert>
            ) : null}

            {error ? (
                <Alert variant="destructive">
                    <AlertTitle>Unable to sync latest data</AlertTitle>
                    <AlertDescription>
                        {resolveDigitalTwinErrorMessage(error)}
                    </AlertDescription>
                </Alert>
            ) : null}

            <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                <form onSubmit={handleSubmit} className="space-y-6">
                    <Card className="border-border/70">
                        <CardHeader>
                            <CardTitle>Metadata</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="category">Category</Label>
                                <Controller
                                    name="categoryId"
                                    control={form.control}
                                    render={({ field }) => (
                                        <Select
                                            value={field.value}
                                            onValueChange={field.onChange}
                                            disabled={
                                                categoriesQuery.isLoading ||
                                                detailCategoryOptions.length ===
                                                    0
                                            }
                                        >
                                            <SelectTrigger id="category">
                                                <SelectValue placeholder="Select a category" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {detailCategoryOptions.map(
                                                    (option) => (
                                                        <SelectItem
                                                            key={option.value}
                                                            value={String(
                                                                option.value,
                                                            )}
                                                        >
                                                            {option.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    )}
                                />
                                {form.formState.errors.categoryId ? (
                                    <p className="text-xs text-destructive">
                                        {
                                            form.formState.errors.categoryId
                                                .message
                                        }
                                    </p>
                                ) : null}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="code">Internal code</Label>
                                <Input
                                    id="code"
                                    placeholder="Optional sku or reference"
                                    {...form.register('code')}
                                />
                                {form.formState.errors.code ? (
                                    <p className="text-xs text-destructive">
                                        {form.formState.errors.code.message}
                                    </p>
                                ) : (
                                    <p className="text-xs text-muted-foreground">
                                        Appears in admin tools, hidden from
                                        buyers.
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="title">Title</Label>
                                <Input
                                    id="title"
                                    placeholder="e.g. EV Battery Enclosure"
                                    {...form.register('title')}
                                />
                                {form.formState.errors.title ? (
                                    <p className="text-xs text-destructive">
                                        {form.formState.errors.title.message}
                                    </p>
                                ) : null}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="version">Version</Label>
                                <Input
                                    id="version"
                                    placeholder="1.0.0"
                                    {...form.register('version')}
                                />
                                {form.formState.errors.version ? (
                                    <p className="text-xs text-destructive">
                                        {form.formState.errors.version.message}
                                    </p>
                                ) : (
                                    <p className="text-xs text-muted-foreground">
                                        Use semantic versioning where possible.
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="summary">Summary</Label>
                                <Textarea
                                    id="summary"
                                    rows={4}
                                    placeholder="What does this digital twin represent?"
                                    {...form.register('summary')}
                                />
                                {form.formState.errors.summary ? (
                                    <p className="text-xs text-destructive">
                                        {form.formState.errors.summary.message}
                                    </p>
                                ) : (
                                    <p className="text-xs text-muted-foreground">
                                        Buyers see this description in the
                                        library.
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="revisionNotes">
                                    Revision notes
                                </Label>
                                <Textarea
                                    id="revisionNotes"
                                    rows={3}
                                    placeholder="Explain what changed in this version"
                                    {...form.register('revisionNotes')}
                                />
                                {form.formState.errors.revisionNotes ? (
                                    <p className="text-xs text-destructive">
                                        {
                                            form.formState.errors.revisionNotes
                                                .message
                                        }
                                    </p>
                                ) : (
                                    <p className="text-xs text-muted-foreground">
                                        Helps buyers understand the delta from
                                        prior releases.
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border/70">
                        <CardHeader>
                            <CardTitle>Tags</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex flex-wrap gap-2">
                                {tags.map((tag) => (
                                    <Badge
                                        key={tag}
                                        variant="secondary"
                                        className="flex items-center gap-2"
                                    >
                                        {tag}
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="h-4 w-4 p-0"
                                            onClick={() => handleRemoveTag(tag)}
                                        >
                                            <X className="h-3 w-3" />
                                        </Button>
                                    </Badge>
                                ))}
                                {tags.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Add discoverability tags like materials
                                        or processes.
                                    </p>
                                ) : null}
                            </div>
                            <div className="flex flex-col gap-2 md:flex-row md:items-center">
                                <Input
                                    value={tagDraft}
                                    onChange={(event) =>
                                        setTagDraft(event.currentTarget.value)
                                    }
                                    placeholder="Add tag"
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            event.preventDefault();
                                            handleAddTag();
                                        }
                                    }}
                                />
                                <Button
                                    type="button"
                                    onClick={handleAddTag}
                                    variant="outline"
                                    disabled={!tagDraft.trim()}
                                >
                                    <PlusCircle className="mr-2 h-4 w-4" /> Add
                                    tag
                                </Button>
                            </div>
                            {form.formState.errors.tags ? (
                                <p className="text-xs text-destructive">
                                    {form.formState.errors.tags.message}
                                </p>
                            ) : null}
                        </CardContent>
                    </Card>

                    <Card className="border-border/70">
                        <CardHeader>
                            <CardTitle>Specifications</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {specFields.map((field, index) => (
                                <div
                                    key={field.id}
                                    className="grid gap-3 md:grid-cols-[1fr_1fr_160px_auto]"
                                >
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor={`spec-name-${field.id}`}
                                        >
                                            Name
                                        </Label>
                                        <Input
                                            id={`spec-name-${field.id}`}
                                            placeholder="e.g. Material"
                                            {...form.register(
                                                `specs.${index}.name`,
                                            )}
                                        />
                                        {specErrors[index]?.name ? (
                                            <p className="text-xs text-destructive">
                                                {
                                                    specErrors[index]?.name
                                                        ?.message
                                                }
                                            </p>
                                        ) : null}
                                    </div>
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor={`spec-value-${field.id}`}
                                        >
                                            Value
                                        </Label>
                                        <Input
                                            id={`spec-value-${field.id}`}
                                            placeholder="e.g. 6061-T6"
                                            {...form.register(
                                                `specs.${index}.value`,
                                            )}
                                        />
                                        {specErrors[index]?.value ? (
                                            <p className="text-xs text-destructive">
                                                {
                                                    specErrors[index]?.value
                                                        ?.message
                                                }
                                            </p>
                                        ) : null}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor={`spec-uom-${field.id}`}>
                                            UoM
                                        </Label>
                                        <Input
                                            id={`spec-uom-${field.id}`}
                                            placeholder="Optional"
                                            {...form.register(
                                                `specs.${index}.uom`,
                                            )}
                                        />
                                        {specErrors[index]?.uom ? (
                                            <p className="text-xs text-destructive">
                                                {
                                                    specErrors[index]?.uom
                                                        ?.message
                                                }
                                            </p>
                                        ) : null}
                                    </div>
                                    <div className="flex items-end justify-end">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Remove spec"
                                            disabled={specFields.length === 1}
                                            onClick={() => remove(index)}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    append({ name: '', value: '', uom: '' })
                                }
                            >
                                <PlusCircle className="mr-2 h-4 w-4" /> Add
                                specification
                            </Button>
                            {form.formState.errors.specs &&
                            !Array.isArray(form.formState.errors.specs) ? (
                                <p className="text-xs text-destructive">
                                    {form.formState.errors.specs.message}
                                </p>
                            ) : null}
                        </CardContent>
                    </Card>

                    <div className="flex flex-wrap items-center justify-end gap-3">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() =>
                                form.reset(
                                    mapDigitalTwinToFormValues(digitalTwin),
                                )
                            }
                        >
                            Reset changes
                        </Button>
                        <Button type="submit" disabled={!canSave}>
                            <Save className="mr-2 h-4 w-4" /> Save changes
                        </Button>
                    </div>
                </form>

                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Status & visibility</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex flex-wrap items-center gap-2">
                                <StatusBadge status={digitalTwin.status} />
                                {digitalTwin.visibility ? (
                                    <Badge variant="secondary">
                                        {digitalTwin.visibility === 'public'
                                            ? 'Public'
                                            : digitalTwin.visibility}
                                    </Badge>
                                ) : null}
                            </div>
                            <dl className="space-y-2 text-sm text-muted-foreground">
                                <div className="flex items-center justify-between">
                                    <dt>Category</dt>
                                    <dd className="text-foreground">
                                        {digitalTwin.category?.name ??
                                            'Uncategorised'}
                                    </dd>
                                </div>
                                <div className="flex items-center justify-between">
                                    <dt>Version</dt>
                                    <dd className="text-foreground">
                                        {digitalTwin.version ?? '—'}
                                    </dd>
                                </div>
                                <div className="flex items-center justify-between">
                                    <dt>Updated</dt>
                                    <dd className="text-foreground">
                                        {digitalTwin.updated_at
                                            ? formatDistanceToNow(
                                                  new Date(
                                                      digitalTwin.updated_at,
                                                  ),
                                                  { addSuffix: true },
                                              )
                                            : '—'}
                                    </dd>
                                </div>
                            </dl>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    variant="secondary"
                                    className="gap-2"
                                    onClick={handlePublish}
                                    disabled={
                                        isPublished || publishTwin.isPending
                                    }
                                >
                                    Publish
                                </Button>
                                <Button
                                    variant="outline"
                                    className="gap-2"
                                    onClick={handleArchive}
                                    disabled={
                                        isArchived || archiveTwin.isPending
                                    }
                                >
                                    <Archive className="mr-2 h-4 w-4" /> Archive
                                </Button>
                                <Button
                                    variant="destructive"
                                    className="gap-2"
                                    onClick={() => setDeleteDialogOpen(true)}
                                >
                                    <Trash2 className="mr-2 h-4 w-4" /> Delete
                                </Button>
                            </div>
                            <Button
                                variant="ghost"
                                className="gap-2"
                                onClick={() =>
                                    navigate(
                                        `/app/library/digital-twins/${digitalTwin.id}`,
                                    )
                                }
                            >
                                View library entry
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Audit timeline</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {auditEventsQuery.isLoading ? (
                                <TimelineSkeleton />
                            ) : auditEventsQuery.error ? (
                                <Alert variant="destructive">
                                    <AlertTitle>
                                        Unable to load audit events
                                    </AlertTitle>
                                    <AlertDescription>
                                        {resolveDigitalTwinErrorMessage(
                                            auditEventsQuery.error,
                                        )}
                                    </AlertDescription>
                                </Alert>
                            ) : auditEvents.length === 0 ? (
                                <Alert>
                                    <AlertTitle>
                                        No audit history yet
                                    </AlertTitle>
                                    <AlertDescription>
                                        Publish, archive, or update this twin to
                                        populate the timeline.
                                    </AlertDescription>
                                </Alert>
                            ) : (
                                <ol className="space-y-4">
                                    {auditEvents.map((event) => (
                                        <AuditEventItem
                                            key={event.id}
                                            event={event}
                                        />
                                    ))}
                                </ol>
                            )}

                            {auditEventsQuery.hasNextPage ? (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        auditEventsQuery.fetchNextPage()
                                    }
                                    disabled={
                                        auditEventsQuery.isFetchingNextPage
                                    }
                                    className="w-full"
                                >
                                    {auditEventsQuery.isFetchingNextPage ? (
                                        <>
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />{' '}
                                            Loading more
                                        </>
                                    ) : (
                                        'Load more'
                                    )}
                                </Button>
                            ) : null}
                        </CardContent>
                    </Card>
                </div>
            </div>

            <Card className="border-border/70">
                <CardHeader>
                    <CardTitle>Assets</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <form
                        className="grid gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto]"
                        onSubmit={handleAssetFormSubmit}
                    >
                        <Input
                            ref={fileInputRef}
                            type="file"
                            accept=".pdf,.zip,.stl,.step,.igs,.iges,.jpg,.jpeg,.png,.json,.csv,.txt,.dwg,.dxf"
                            onChange={(event) =>
                                setAssetFile(
                                    event.currentTarget.files?.[0] ?? null,
                                )
                            }
                        />
                        <div className="flex flex-col gap-2">
                            <Label className="text-xs text-muted-foreground">
                                Asset type
                            </Label>
                            <Select
                                value={assetType}
                                onValueChange={(value) =>
                                    setAssetType(value as AssetTypeValue)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Choose type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {ASSET_TYPE_OPTIONS.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <label className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Checkbox
                                    checked={assetIsPrimary}
                                    onCheckedChange={(checked) =>
                                        setAssetIsPrimary(Boolean(checked))
                                    }
                                />
                                Set as primary asset
                            </label>
                        </div>
                        <Button
                            type="submit"
                            disabled={!assetFile || uploadAsset.isPending}
                        >
                            <Upload className="mr-2 h-4 w-4" />{' '}
                            {uploadAsset.isPending
                                ? 'Uploading...'
                                : 'Upload asset'}
                        </Button>
                    </form>
                    {assetFile ? (
                        <p className="text-xs text-muted-foreground">
                            Selected file:{' '}
                            <span className="font-medium text-foreground">
                                {assetFile.name}
                            </span>{' '}
                            · {formatFileSize(assetFile.size)}
                        </p>
                    ) : (
                        <p className="text-xs text-muted-foreground">
                            Max file size follows the document policy in
                            config/filesystems. Use descriptive filenames for
                            suppliers.
                        </p>
                    )}

                    {digitalTwin.assets.length === 0 ? (
                        <Alert>
                            <AlertTitle>No assets uploaded</AlertTitle>
                            <AlertDescription>
                                Upload CAD, drawings, or reference PDFs so
                                buyers can reuse this twin.
                            </AlertDescription>
                        </Alert>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[640px] border-collapse">
                                <thead className="border-b bg-muted/40 text-left text-sm font-medium text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-3">Filename</th>
                                        <th className="px-4 py-3">Type</th>
                                        <th className="px-4 py-3">Size</th>
                                        <th className="px-4 py-3">Uploaded</th>
                                        <th className="px-4 py-3 text-right">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {digitalTwin.assets.map((asset) => (
                                        <tr
                                            key={asset.id}
                                            className="border-b last:border-b-0"
                                        >
                                            <td className="px-4 py-4 align-top">
                                                <div className="font-medium text-foreground">
                                                    {asset.filename}
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    {asset.mime ??
                                                        'application/octet-stream'}
                                                </p>
                                                {asset.is_primary ? (
                                                    <Badge className="mt-2">
                                                        Primary
                                                    </Badge>
                                                ) : null}
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <Badge variant="outline">
                                                    {asset.type ?? 'Other'}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-4 align-top text-sm text-muted-foreground">
                                                {formatFileSize(
                                                    asset.size_bytes,
                                                )}
                                            </td>
                                            <td className="px-4 py-4 align-top text-sm text-muted-foreground">
                                                {asset.created_at
                                                    ? formatDistanceToNow(
                                                          new Date(
                                                              asset.created_at,
                                                          ),
                                                          { addSuffix: true },
                                                      )
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-4 text-right align-top">
                                                <div className="flex justify-end gap-2">
                                                    {asset.download_url ? (
                                                        <Button
                                                            asChild
                                                            variant="ghost"
                                                            size="sm"
                                                            className="gap-2"
                                                        >
                                                            <a
                                                                href={
                                                                    asset.download_url
                                                                }
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                            >
                                                                <Download className="h-4 w-4" />{' '}
                                                                Download
                                                            </a>
                                                        </Button>
                                                    ) : null}
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-destructive"
                                                        onClick={() =>
                                                            setAssetPendingDelete(
                                                                asset,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="mr-2 h-4 w-4" />{' '}
                                                        Remove
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardContent>
            </Card>

            <ConfirmDialog
                open={deleteDialogOpen}
                onOpenChange={setDeleteDialogOpen}
                title="Delete digital twin?"
                description={`Deleting ${digitalTwin.title} removes specs and assets.`}
                confirmLabel="Delete"
                confirmVariant="destructive"
                isProcessing={deleteTwin.isPending}
                onConfirm={handleDeleteTwin}
            />

            <ConfirmDialog
                open={Boolean(assetPendingDelete)}
                onOpenChange={(open) =>
                    setAssetPendingDelete(open ? assetPendingDelete : null)
                }
                title="Remove asset?"
                description={`Are you sure you want to delete ${assetPendingDelete?.filename ?? 'this asset'}?`}
                confirmLabel="Remove"
                confirmVariant="destructive"
                isProcessing={deleteAsset.isPending}
                onConfirm={handleConfirmAssetDelete}
            />
        </section>
    );
}

function StatusBadge({
    status,
}: {
    status?: AdminDigitalTwinListItem['status'] | null;
}) {
    if (!status) {
        return <Badge variant="outline">Unknown</Badge>;
    }

    switch (status) {
        case 'draft':
            return <Badge variant="secondary">Draft</Badge>;
        case 'published':
            return <Badge>Published</Badge>;
        case 'archived':
            return <Badge variant="outline">Archived</Badge>;
        default:
            return <Badge variant="outline">{status}</Badge>;
    }
}

function AdminDigitalTwinDetailSkeleton() {
    return (
        <section className="space-y-6">
            <Helmet>
                <title>Admin · Digital twin</title>
            </Helmet>
            <Skeleton className="h-10 w-64" />
            <Skeleton className="h-6 w-full" />
            <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                <Card>
                    <CardContent className="space-y-4 pt-6">
                        <Skeleton className="h-10 w-full" />
                        <Skeleton className="h-10 w-full" />
                        <Skeleton className="h-24 w-full" />
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="space-y-4 pt-6">
                        <Skeleton className="h-8 w-48" />
                        <Skeleton className="h-8 w-48" />
                    </CardContent>
                </Card>
            </div>
            <Card>
                <CardContent className="space-y-4 pt-6">
                    <Skeleton className="h-10 w-full" />
                    <Skeleton className="h-10 w-full" />
                </CardContent>
            </Card>
        </section>
    );
}

function TimelineSkeleton() {
    return (
        <div className="space-y-4">
            {Array.from({ length: 3 }).map((_, index) => (
                <div key={index} className="flex items-start gap-3">
                    <Skeleton className="mt-1 h-2 w-2 rounded-full" />
                    <div className="flex-1 space-y-2">
                        <Skeleton className="h-4 w-1/2" />
                        <Skeleton className="h-3 w-3/4" />
                    </div>
                </div>
            ))}
        </div>
    );
}

function AuditEventItem({ event }: { event: AdminDigitalTwinAuditEvent }) {
    const actorLabel = getAuditEventActorLabel(event);
    const metaLines = getAuditEventMetaLines(event);
    const timestamp = event.created_at
        ? formatDistanceToNow(new Date(event.created_at), { addSuffix: true })
        : '—';

    return (
        <li className="flex gap-3">
            <span
                className="mt-1 h-2 w-2 rounded-full bg-primary"
                aria-hidden
            />
            <div className="flex-1 space-y-1">
                <div className="flex flex-wrap items-center justify-between gap-2 text-sm font-medium text-foreground">
                    <span>{getAuditEventLabel(event)}</span>
                    <time className="text-xs text-muted-foreground">
                        {timestamp}
                    </time>
                </div>
                {actorLabel ? (
                    <p className="text-xs text-muted-foreground">
                        By {actorLabel}
                    </p>
                ) : null}
                {metaLines.length > 0 ? (
                    <ul className="list-disc space-y-1 pl-5 text-xs text-muted-foreground">
                        {metaLines.map((line, index) => (
                            <li key={`${event.id}-meta-${index}`}>{line}</li>
                        ))}
                    </ul>
                ) : null}
            </div>
        </li>
    );
}

function getAuditEventLabel(event: AdminDigitalTwinAuditEvent): string {
    switch (event.event) {
        case 'created':
            return 'Digital twin created';
        case 'updated':
            return 'Metadata updated';
        case 'published':
            return 'Published to the buyer library';
        case 'archived':
            return 'Archived';
        case 'asset_added':
            return event.meta?.filename
                ? `Asset added: ${event.meta.filename}`
                : 'Asset added';
        case 'asset_removed':
            return event.meta?.filename
                ? `Asset removed: ${event.meta.filename}`
                : 'Asset removed';
        case 'spec_changed':
            return 'Specifications updated';
        default:
            return event.event ?? 'Audit event';
    }
}

function getAuditEventMetaLines(event: AdminDigitalTwinAuditEvent): string[] {
    const meta = event.meta;
    if (!meta || typeof meta !== 'object') {
        return [];
    }

    const lines: string[] = [];

    if (Array.isArray(meta.changed) && meta.changed.length > 0) {
        lines.push(`Fields: ${meta.changed.join(', ')}`);
    }

    if (typeof meta.reason === 'string' && meta.reason.length > 0) {
        lines.push(`Reason: ${meta.reason}`);
    }

    if (
        typeof meta.type === 'string' &&
        meta.type.length > 0 &&
        !String(meta.type).startsWith('[')
    ) {
        lines.push(`Type: ${meta.type}`);
    }

    if (typeof meta.filename === 'string' && meta.filename.length > 0) {
        lines.push(`Filename: ${meta.filename}`);
    }

    if (typeof meta.asset_id !== 'undefined') {
        lines.push(`Asset #${meta.asset_id}`);
    }

    if (
        typeof meta.title === 'string' &&
        meta.title.length > 0 &&
        event.event === 'created'
    ) {
        lines.push(`Title: ${meta.title}`);
    }

    if (lines.length === 0) {
        lines.push(JSON.stringify(meta));
    }

    return lines;
}

function getAuditEventActorLabel(
    event: AdminDigitalTwinAuditEvent,
): string | null {
    if (!event.actor) {
        return null;
    }

    return event.actor.name ?? event.actor.email ?? `User #${event.actor.id}`;
}

function formatFileSize(bytes?: number | null): string {
    if (typeof bytes !== 'number' || Number.isNaN(bytes) || bytes <= 0) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;

    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex += 1;
    }

    return `${size.toFixed(unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
}
