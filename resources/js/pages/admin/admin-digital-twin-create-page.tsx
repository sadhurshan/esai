import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import { Controller, useFieldArray, useForm, useWatch } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { PlusCircle, Save, Trash2, X } from 'lucide-react';

import Heading from '@/components/heading';
import { AccessDeniedPage } from '@/pages/errors/access-denied-page';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { publishToast } from '@/components/ui/use-toast';
import { useAuth } from '@/contexts/auth-context';
import {
	adminDigitalTwinFormSchema,
	ADMIN_DIGITAL_TWIN_FORM_DEFAULTS,
	flattenDigitalTwinCategories,
	resolveDigitalTwinErrorMessage,
} from './digital-twin-form-utils';
import type { AdminDigitalTwinFormValues } from './digital-twin-form-utils';

import {
	useAdminDigitalTwinCategories,
	useCreateAdminDigitalTwin,
} from '@/hooks/api/digital-twins';

export function AdminDigitalTwinCreatePage() {
	const navigate = useNavigate();
	const { isAdmin } = useAuth();
	const createTwin = useCreateAdminDigitalTwin();
	const categoriesQuery = useAdminDigitalTwinCategories();
	const categoryOptions = useMemo(() => flattenDigitalTwinCategories(categoriesQuery.categories), [categoriesQuery.categories]);
	const [tagDraft, setTagDraft] = useState('');

	const form = useForm<AdminDigitalTwinFormValues>({
		resolver: zodResolver(adminDigitalTwinFormSchema),
		defaultValues: ADMIN_DIGITAL_TWIN_FORM_DEFAULTS,
	});

	const { fields: specFields, append, remove } = useFieldArray({ control: form.control, name: 'specs' });
	const tags = useWatch({ control: form.control, name: 'tags' });
	const specErrors = Array.isArray(form.formState.errors.specs) ? form.formState.errors.specs : [];

	useEffect(() => {
		if (categoryOptions.length === 0) {
			return;
		}

		const current = form.getValues('categoryId');
		if (!current) {
			form.setValue('categoryId', String(categoryOptions[0].value), { shouldDirty: false });
		}
	}, [categoryOptions, form]);

	if (!isAdmin) {
		return <AccessDeniedPage />;
	}

	const handleAddTag = () => {
		const cleanValue = tagDraft.trim();
		if (!cleanValue) {
			return;
		}

		const normalized = cleanValue.toLowerCase();
		const exists = tags.some((tag) => tag.toLowerCase() === normalized);
		if (exists) {
			publishToast({ title: 'Tag already added', description: `${cleanValue} is already in the list.` });
			return;
		}

		if (tags.length >= 12) {
			publishToast({
				title: 'Tag limit reached',
				description: 'You can add up to 12 tags per digital twin.',
			});
			return;
		}

		form.setValue('tags', [...tags, cleanValue], { shouldDirty: true, shouldValidate: true });
		setTagDraft('');
	};

	const handleRemoveTag = (tagToRemove: string) => {
		const nextTags = tags.filter((tag) => tag !== tagToRemove);
		form.setValue('tags', nextTags, { shouldDirty: true, shouldValidate: true });
	};

	const handleSubmit = form.handleSubmit(async (values) => {
		try {
			const payload = {
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
				visibility: 'public',
			};

			const response = await createTwin.mutateAsync(payload);
			const createdId = response.data?.digital_twin?.id;

			publishToast({
				title: 'Digital twin draft created',
				description: 'Add assets and publish when you are ready.',
				variant: 'success',
			});

			if (createdId) {
				navigate(`/app/admin/digital-twins/${createdId}`);
			} else {
				navigate('/app/admin/digital-twins');
			}
		} catch (error) {
			publishToast({
				title: 'Unable to create digital twin',
				description: resolveDigitalTwinErrorMessage(error),
				variant: 'destructive',
			});
		}
	});

	const categoryError = categoriesQuery.error;
	const isSubmitting = createTwin.isPending;
	const canSubmit = !isSubmitting && (categoryOptions.length > 0 || Boolean(form.getValues('categoryId')));

	return (
		<div className="space-y-6">
			<Helmet>
				<title>Admin · Create digital twin</title>
			</Helmet>
			<Heading
				title="Create digital twin"
				description="Capture metadata, specs, and tags before uploading assets."
			/>

			{categoryError ? (
				<Alert variant="destructive">
					<AlertTitle>Unable to load categories</AlertTitle>
					<AlertDescription>{resolveDigitalTwinErrorMessage(categoryError)}</AlertDescription>
				</Alert>
			) : null}

			{!categoriesQuery.isLoading && categoryOptions.length === 0 ? (
				<Alert>
					<AlertTitle>No categories available</AlertTitle>
					<AlertDescription>
						Create at least one digital twin category before publishing content. You can manage categories from the admin
						dashboard.
					</AlertDescription>
				</Alert>
			) : null}

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
									<Select value={field.value} onValueChange={field.onChange} disabled={categoriesQuery.isLoading || categoryOptions.length === 0}>
										<SelectTrigger id="category">
											<SelectValue placeholder="Select a category" />
										</SelectTrigger>
										<SelectContent>
											{categoryOptions.map((option) => (
												<SelectItem key={option.value} value={String(option.value)}>
													{option.label}
												</SelectItem>
											))}
										</SelectContent>
									</Select>
								)}
							/>
							{form.formState.errors.categoryId ? (
								<p className="text-xs text-destructive">{form.formState.errors.categoryId.message}</p>
							) : null}
						</div>
						<div className="space-y-2">
							<Label htmlFor="code">Internal code</Label>
							<Input id="code" placeholder="Optional sku or reference" {...form.register('code')} />
							{form.formState.errors.code ? (
								<p className="text-xs text-destructive">{form.formState.errors.code.message}</p>
							) : (
								<p className="text-xs text-muted-foreground">Appears in admin tools, hidden from buyers.</p>
							)}
						</div>
						<div className="space-y-2">
							<Label htmlFor="title">Title</Label>
							<Input id="title" placeholder="e.g. EV Battery Enclosure" {...form.register('title')} />
							{form.formState.errors.title ? (
								<p className="text-xs text-destructive">{form.formState.errors.title.message}</p>
							) : null}
						</div>
						<div className="space-y-2">
							<Label htmlFor="version">Version</Label>
							<Input id="version" placeholder="1.0.0" {...form.register('version')} />
							{form.formState.errors.version ? (
								<p className="text-xs text-destructive">{form.formState.errors.version.message}</p>
							) : (
								<p className="text-xs text-muted-foreground">Use semantic versioning where possible.</p>
							)}
						</div>
						<div className="md:col-span-2 space-y-2">
							<Label htmlFor="summary">Summary</Label>
							<Textarea id="summary" rows={4} placeholder="What does this digital twin represent?" {...form.register('summary')} />
							{form.formState.errors.summary ? (
								<p className="text-xs text-destructive">{form.formState.errors.summary.message}</p>
							) : (
								<p className="text-xs text-muted-foreground">Buyers see this description in the library.</p>
							)}
						</div>
						<div className="md:col-span-2 space-y-2">
							<Label htmlFor="revisionNotes">Revision notes</Label>
							<Textarea id="revisionNotes" rows={3} placeholder="Explain what changed in this version" {...form.register('revisionNotes')} />
							{form.formState.errors.revisionNotes ? (
								<p className="text-xs text-destructive">{form.formState.errors.revisionNotes.message}</p>
							) : (
								<p className="text-xs text-muted-foreground">Helps buyers understand the delta from prior releases.</p>
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
								<Badge key={tag} variant="secondary" className="flex items-center gap-2">
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
								<p className="text-sm text-muted-foreground">Add discoverability tags like materials or processes.</p>
							) : null}
						</div>
						<div className="flex flex-col gap-2 md:flex-row md:items-center">
							<Input
								value={tagDraft}
								onChange={(event) => setTagDraft(event.currentTarget.value)}
								placeholder="Add tag"
								onKeyDown={(event) => {
									if (event.key === 'Enter') {
										event.preventDefault();
										handleAddTag();
									}
								}}
							/>
							<Button type="button" onClick={handleAddTag} variant="outline" disabled={!tagDraft.trim()}>
								<PlusCircle className="mr-2 h-4 w-4" /> Add tag
							</Button>
						</div>
						{form.formState.errors.tags ? (
							<p className="text-xs text-destructive">{form.formState.errors.tags.message}</p>
						) : null}
					</CardContent>
				</Card>

				<Card className="border-border/70">
					<CardHeader>
						<CardTitle>Specifications</CardTitle>
					</CardHeader>
					<CardContent className="space-y-4">
						{specFields.map((field, index) => (
							<div key={field.id} className="grid gap-3 md:grid-cols-[1fr_1fr_160px_auto]">
								<div className="space-y-2">
									<Label htmlFor={`spec-name-${field.id}`}>Name</Label>
									<Input id={`spec-name-${field.id}`} placeholder="e.g. Material" {...form.register(`specs.${index}.name`)} />
									{specErrors[index]?.name ? (
										<p className="text-xs text-destructive">{specErrors[index]?.name?.message}</p>
									) : null}
								</div>
								<div className="space-y-2">
									<Label htmlFor={`spec-value-${field.id}`}>Value</Label>
									<Input id={`spec-value-${field.id}`} placeholder="e.g. 6061-T6" {...form.register(`specs.${index}.value`)} />
									{specErrors[index]?.value ? (
										<p className="text-xs text-destructive">{specErrors[index]?.value?.message}</p>
									) : null}
								</div>
								<div className="space-y-2">
									<Label htmlFor={`spec-uom-${field.id}`}>UoM</Label>
									<Input id={`spec-uom-${field.id}`} placeholder="Optional" {...form.register(`specs.${index}.uom`)} />
									{specErrors[index]?.uom ? (
										<p className="text-xs text-destructive">{specErrors[index]?.uom?.message}</p>
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
						<Button type="button" variant="outline" onClick={() => append({ name: '', value: '', uom: '' })}>
							<PlusCircle className="mr-2 h-4 w-4" /> Add specification
						</Button>
						{form.formState.errors.specs && !Array.isArray(form.formState.errors.specs) ? (
							<p className="text-xs text-destructive">{form.formState.errors.specs.message}</p>
						) : null}
					</CardContent>
				</Card>

				<div className="flex items-center justify-end gap-3">
					<Button type="button" variant="ghost" onClick={() => navigate('/app/admin/digital-twins')}>
						Cancel
					</Button>
					<Button type="submit" disabled={!canSubmit}>
						<Save className="mr-2 h-4 w-4" /> Save draft
					</Button>
				</div>
			</form>
		</div>
	);
}


