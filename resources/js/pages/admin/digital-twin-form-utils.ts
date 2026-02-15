import type {
    AdminDigitalTwinCategoryNode,
    AdminDigitalTwinDetail,
} from '@/sdk';
import { HttpError } from '@/sdk';
import { z } from 'zod';

export const digitalTwinSpecSchema = z.object({
    name: z.string().min(1, 'Spec name is required'),
    value: z.string().min(1, 'Spec value is required'),
    uom: z
        .string()
        .max(40, 'UoM must be 40 characters or fewer')
        .optional()
        .nullable(),
});

export const adminDigitalTwinFormSchema = z.object({
    categoryId: z.string().min(1, 'Category is required'),
    code: z
        .string()
        .max(120, 'Code must be 120 characters or fewer')
        .optional()
        .nullable(),
    title: z.string().min(3, 'Title is required'),
    summary: z.string().max(2000, 'Summary is too long').optional().nullable(),
    version: z.string().min(1, 'Version is required'),
    revisionNotes: z
        .string()
        .max(2000, 'Revision notes are too long')
        .optional()
        .nullable(),
    tags: z
        .array(z.string().min(1).max(40, 'Tag is too long'))
        .max(12, 'Limit to 12 tags')
        .default([]),
    specs: z
        .array(digitalTwinSpecSchema)
        .min(1, 'Add at least one specification'),
});

export type AdminDigitalTwinFormValues = z.infer<
    typeof adminDigitalTwinFormSchema
>;

export const ADMIN_DIGITAL_TWIN_FORM_DEFAULTS: AdminDigitalTwinFormValues = {
    categoryId: '',
    code: '',
    title: '',
    summary: '',
    version: '1.0.0',
    revisionNotes: '',
    tags: [],
    specs: [{ name: '', value: '', uom: '' }],
};

export function flattenDigitalTwinCategories(
    nodes: AdminDigitalTwinCategoryNode[],
    depth = 0,
): Array<{ value: number; label: string }> {
    return nodes.flatMap((node) => {
        const indent = depth > 0 ? `${' '.repeat(depth * 2)}> ` : '';
        const current = [{ value: node.id, label: `${indent}${node.name}` }];
        const children = node.children
            ? flattenDigitalTwinCategories(node.children, depth + 1)
            : [];
        return [...current, ...children];
    });
}

export function resolveDigitalTwinErrorMessage(error: unknown): string {
    if (error instanceof HttpError) {
        const detail =
            typeof error.body === 'object' &&
            error.body !== null &&
            'message' in error.body
                ? (error.body.message as string | undefined)
                : undefined;
        if (typeof detail === 'string' && detail.length > 0) {
            return detail;
        }
    }

    if (error instanceof Error) {
        return error.message;
    }

    return 'Something went wrong. Please try again.';
}

export function mapDigitalTwinToFormValues(
    digitalTwin: AdminDigitalTwinDetail,
): AdminDigitalTwinFormValues {
    const specs =
        digitalTwin.specs.length > 0
            ? digitalTwin.specs
            : [{ name: '', value: '', uom: '' }];

    return {
        categoryId:
            digitalTwin.category?.id != null
                ? String(digitalTwin.category.id)
                : '',
        code: digitalTwin.code ?? '',
        title: digitalTwin.title ?? '',
        summary: digitalTwin.summary ?? '',
        version: digitalTwin.version ?? '',
        revisionNotes: digitalTwin.revision_notes ?? '',
        tags: digitalTwin.tags ?? [],
        specs: specs.map((spec) => ({
            name: spec.name ?? '',
            value: spec.value ?? '',
            uom: spec.uom ?? '',
        })),
    } satisfies AdminDigitalTwinFormValues;
}
