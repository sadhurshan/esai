import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link, Navigate, useNavigate } from 'react-router-dom';
import { Trash2 } from 'lucide-react';

import { Branding } from '@/config/branding';
import { DOCUMENT_ACCEPT_LABEL, DOCUMENT_INPUT_ACCEPT } from '@/config/documents';
import { useAuth } from '@/contexts/auth-context';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { HttpError } from '@/sdk';

const domainRegex = /^(?!-)(?:[a-z0-9-]+\.)+[a-z]{2,}$/i;
const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/;

const documentTypeOptions = [
    { value: 'registration', label: 'Registration certificate' },
    { value: 'tax', label: 'Tax certificate' },
    { value: 'esg', label: 'ESG or compliance policy' },
    { value: 'other', label: 'Other supporting document' },
] as const;

type DocumentType = (typeof documentTypeOptions)[number]['value'];

interface DocumentDraft {
    id: string;
    type: DocumentType;
    file: File | null;
}

const MAX_DOCUMENTS = 5;

function nextDocumentId(): string {
    const cryptoApi = typeof globalThis !== 'undefined' ? (globalThis.crypto as Crypto | undefined) : undefined;
    if (cryptoApi && typeof cryptoApi.randomUUID === 'function') {
        return cryptoApi.randomUUID();
    }
    return `doc-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
}

function createDocumentDraft(): DocumentDraft {
    return {
        id: nextDocumentId(),
        type: 'registration',
        file: null,
    };
}

const registerSchema = z
    .object({
        name: z
            .string({ required_error: 'Full name is required.' })
            .min(2, 'Enter your full name.')
            .max(160, 'Full name is too long.'),
        email: z
            .string({ required_error: 'Email is required.' })
            .min(1, 'Email is required.')
            .email('Enter a valid email address.'),
        password: z
            .string({ required_error: 'Password is required.' })
            .min(8, 'Use at least 8 characters.')
            .regex(passwordRegex, 'Use upper, lower, number, and symbol.'),
        passwordConfirmation: z
            .string({ required_error: 'Confirm your password.' })
            .min(8, 'Confirm your password.'),
        companyName: z
            .string({ required_error: 'Company name is required.' })
            .min(2, 'Company name is required.')
            .max(160, 'Company name is too long.'),
        companyDomain: z
            .string({ required_error: 'Company domain is required.' })
            .min(3, 'Company domain is required.')
            .regex(domainRegex, 'Enter a valid domain, e.g. example.com')
            .max(191, 'Domain is too long.'),
        registrationNo: z
            .string({ required_error: 'Registration number is required.' })
            .min(3, 'Registration number is required.')
            .max(120, 'Registration number is too long.'),
        taxId: z
            .string({ required_error: 'Tax ID is required.' })
            .min(3, 'Tax ID is required.')
            .max(120, 'Tax ID is too long.'),
        website: z
            .string({ required_error: 'Company website is required.' })
            .url('Enter a valid URL, e.g. https://example.com')
            .max(191, 'Website URL is too long.'),
        phone: z
            .string({ required_error: 'Company phone is required.' })
            .min(3, 'Company phone is required.')
            .max(60, 'Phone number is too long.'),
        address: z
            .string()
            .max(500, 'Address is too long.')
            .optional()
            .or(z.literal('')),
        country: z
            .string()
            .length(2, 'Use the 2-letter ISO code, e.g. US.')
            .optional()
            .or(z.literal('')),
    })
    .refine((values) => values.password === values.passwordConfirmation, {
        message: 'Passwords do not match.',
        path: ['passwordConfirmation'],
    });

export type RegisterFormValues = z.infer<typeof registerSchema>;

const serverFieldMap: Record<string, keyof RegisterFormValues> = {
    name: 'name',
    email: 'email',
    password: 'password',
    password_confirmation: 'passwordConfirmation',
    company_name: 'companyName',
    company_domain: 'companyDomain',
    registration_no: 'registrationNo',
    tax_id: 'taxId',
    website: 'website',
    phone: 'phone',
    address: 'address',
    country: 'country',
};

export function RegisterPage() {
    const navigate = useNavigate();
    const { register: registerAccount, state, isAuthenticated } = useAuth();
    const [submitError, setSubmitError] = useState<string | null>(null);
    const [documents, setDocuments] = useState<DocumentDraft[]>([createDocumentDraft()]);
    const [documentsError, setDocumentsError] = useState<string | null>(null);

    const {
        register,
        handleSubmit,
        setError,
        formState: { errors, isSubmitting },
    } = useForm<RegisterFormValues>({
        resolver: zodResolver(registerSchema),
        defaultValues: {
            name: '',
            email: '',
            password: '',
            passwordConfirmation: '',
            companyName: '',
            companyDomain: '',
            registrationNo: '',
            taxId: '',
            website: '',
            phone: '',
            address: '',
            country: '',
        },
    });

    const handleAddDocument = () => {
        setDocumentsError(null);
        setDocuments((prev) => {
            if (prev.length >= MAX_DOCUMENTS) {
                return prev;
            }
            return [...prev, createDocumentDraft()];
        });
    };

    const handleDocumentTypeChange = (id: string, type: DocumentType) => {
        setDocuments((prev) => prev.map((doc) => (doc.id === id ? { ...doc, type } : doc)));
    };

    const handleDocumentFileChange = (id: string, file: File | null) => {
        setDocumentsError(null);
        setDocuments((prev) => prev.map((doc) => (doc.id === id ? { ...doc, file } : doc)));
    };

    const handleRemoveDocument = (id: string) => {
        setDocumentsError(null);
        setDocuments((prev) => {
            const remaining = prev.filter((doc) => doc.id !== id);
            return remaining.length > 0 ? remaining : [createDocumentDraft()];
        });
    };

    const errorMessage = submitError;

    const onSubmit = handleSubmit(async (values) => {
        setSubmitError(null);
        const hasDocuments = documents.length > 0;
        const missingFile = documents.some((doc) => doc.file === null);

        if (!hasDocuments || missingFile) {
            setDocumentsError('Upload at least one supporting document and attach a file for each row.');
            return;
        }

        const documentPayload = documents.map((doc) => ({ type: doc.type, file: doc.file as File }));
        setDocumentsError(null);

        const pickMessage = (value: unknown): string | null => {
            if (typeof value === 'string' && value.trim().length > 0) {
                return value;
            }

            if (Array.isArray(value)) {
                const candidate = value.find((item) => typeof item === 'string' && item.trim().length > 0);
                return candidate ?? null;
            }

            return null;
        };

        const applyServerErrors = (errorBag: Record<string, unknown> | undefined | null): string | null => {
            if (!errorBag) {
                return null;
            }

            let firstMessage: string | null = null;

            Object.entries(errorBag).forEach(([field, messageValue]) => {
                const message = pickMessage(messageValue);
                if (!message) {
                    return;
                }

                if (!firstMessage) {
                    firstMessage = message;
                }

                const mappedField = serverFieldMap[field];
                if (mappedField) {
                    setError(mappedField, { type: 'server', message });
                    return;
                }

                if (field.startsWith('company_documents')) {
                    setDocumentsError(message);
                }
            });

            return firstMessage;
        };

        try {
            const result = await registerAccount({
                name: values.name,
                email: values.email,
                password: values.password,
                passwordConfirmation: values.passwordConfirmation,
                companyName: values.companyName,
                companyDomain: values.companyDomain,
                registrationNo: values.registrationNo,
                taxId: values.taxId,
                website: values.website,
                phone: values.phone.trim(),
                address: values.address?.trim() ? values.address : undefined,
                country: values.country?.trim() ? values.country : undefined,
                companyDocuments: documentPayload,
            });

            const destination = result.requiresEmailVerification ? '/verify-email' : '/app/setup/plan';
            navigate(destination, { replace: true });
        } catch (error) {
            if (error instanceof HttpError) {
                const body = error.body as { message?: string; errors?: Record<string, unknown> } | undefined;
                const validationMessage = applyServerErrors(body?.errors ?? null);
                const backendMessage = typeof body?.message === 'string' && body.message.trim().length > 0 ? body.message : null;
                setSubmitError(validationMessage ?? backendMessage ?? 'Unable to create your workspace.');
                return;
            }

            if (error instanceof Error) {
                setSubmitError(error.message);
                return;
            }

            setSubmitError('Unable to create your workspace.');
        }
    });

    if (isAuthenticated) {
        if (state.requiresEmailVerification) {
            return <Navigate to="/verify-email" replace />;
        }

        const needsPlan = state.requiresPlanSelection || state.company?.requires_plan_selection === true || !state.company?.plan;
        return <Navigate to={needsPlan ? '/app/setup/plan' : '/app'} replace />;
    }

    return (
        <div
            className="flex min-h-screen items-center justify-center bg-slate-950 px-4 py-12 text-slate-100"
            style={{
                backgroundImage: "url('/3d-rendering-big-red-coronavirus-cell-left-with-black-background.jpg')",
                backgroundSize: 'cover',
                backgroundPosition: 'center',
            }}
        >
            <Card className="w-full max-w-4xl border border-white/10 bg-slate-950/80 text-slate-100 shadow-lg backdrop-blur">
                <CardHeader className="items-center text-center space-y-2">
                    <img src={Branding.logo.symbol} alt={Branding.name} className="h-10" />
                    <CardTitle className="text-2xl font-semibold text-white">Create your workspace</CardTitle>
                    <CardDescription className="text-slate-400">
                        The first user becomes the workspace owner. Buyer tools are enabled immediately; supplier features activate
                        after approval.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form className="space-y-6" onSubmit={onSubmit} noValidate>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="name">Full name</Label>
                                <Input className='border-slate-500 rounded-sm py-6' id="name" autoComplete="name" placeholder="Casey Owner" {...register('name')} />
                                {errors.name ? <p className="text-xs text-destructive">{errors.name.message}</p> : null}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="email">Work email</Label>
                                <Input className='border-slate-500 rounded-sm py-6' id="email" type="email" autoComplete="email" placeholder="you@example.com" {...register('email', {
                                    setValueAs: (value) => (typeof value === 'string' ? value.trim() : value),
                                })} />
                                {errors.email ? <p className="text-xs text-destructive">{errors.email.message}</p> : null}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="companyName">Company name</Label>
                                <Input className='border-slate-500 rounded-sm py-6' id="companyName" autoComplete="organization" placeholder="Elements Supply" {...register('companyName', {
                                    setValueAs: (value) => (typeof value === 'string' ? value.trim() : value),
                                })} />
                                {errors.companyName ? <p className="text-xs text-destructive">{errors.companyName.message}</p> : null}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="companyDomain">Company domain</Label>
                                <Input className='border-slate-500 rounded-sm py-6' id="companyDomain" placeholder="example.com" autoComplete="off" {...register('companyDomain', {
                                    setValueAs: (value) => (typeof value === 'string' ? value.trim().toLowerCase() : value),
                                })} />
                                <p className="text-xs text-slate-400">Used for invitation policies and supplier vetting.</p>
                                {errors.companyDomain ? (
                                    <p className="text-xs text-destructive">{errors.companyDomain.message}</p>
                                ) : null}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="registrationNo">Registration number</Label>
                                <Input className='border-slate-500 rounded-sm py-6'
                                    id="registrationNo"
                                    placeholder="REG-123456"
                                    autoComplete="off"
                                    {...register('registrationNo', {
                                        setValueAs: (value) => (typeof value === 'string' ? value.trim() : value),
                                    })}
                                />
                                {errors.registrationNo ? <p className="text-xs text-destructive">{errors.registrationNo.message}</p> : null}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="taxId">Tax ID</Label>
                                <Input className='border-slate-500 rounded-sm py-6'
                                    id="taxId"
                                    placeholder="TAX-999"
                                    autoComplete="off"
                                    {...register('taxId', {
                                        setValueAs: (value) => (typeof value === 'string' ? value.trim() : value),
                                    })}
                                />
                                {errors.taxId ? <p className="text-xs text-destructive">{errors.taxId.message}</p> : null}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="password">Password</Label>
                                <Input className='border-slate-500 rounded-sm py-6' id="password" type="password" autoComplete="new-password" {...register('password')} />
                                <p className="text-xs text-slate-400">Min 8 chars with upper, lower, number & symbol.</p>
                                {errors.password ? <p className="text-xs text-destructive">{errors.password.message}</p> : null}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="passwordConfirmation">Confirm password</Label>
                                <Input className='border-slate-500 rounded-sm py-6' id="passwordConfirmation" type="password" autoComplete="new-password" {...register('passwordConfirmation')} />
                                {errors.passwordConfirmation ? (
                                    <p className="text-xs text-destructive">{errors.passwordConfirmation.message}</p>
                                ) : null}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="website">Company website</Label>
                                <Input className='border-slate-500 rounded-sm py-6'
                                    id="website"
                                    type="url"
                                    autoComplete="url"
                                    placeholder="https://example.com"
                                    {...register('website', {
                                        setValueAs: (value) => (typeof value === 'string' ? value.trim() : value),
                                    })}
                                />
                                {errors.website ? <p className="text-xs text-destructive">{errors.website.message}</p> : null}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="phone">Company phone</Label>
                                <Input className='border-slate-500 rounded-sm py-6' id="phone" type="tel" autoComplete="tel" placeholder="+1 555-0100" {...register('phone', {
                                    setValueAs: (value) => (typeof value === 'string' ? value.trim() : value),
                                })} />
                                {errors.phone ? <p className="text-xs text-destructive">{errors.phone.message}</p> : null}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="country">Country (ISO code)</Label>
                                <Input className='border-slate-500 rounded-sm py-6' id="country" placeholder="US" maxLength={2} {...register('country', {
                                    setValueAs: (value) => (typeof value === 'string' ? value.trim().toUpperCase() : value),
                                })} />
                                {errors.country ? <p className="text-xs text-destructive">{errors.country.message}</p> : null}
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="address">Company address (optional)</Label>
                            <Textarea className='border-slate-500 rounded-sm bg-transparent' id="address" rows={3} placeholder="HQ location" {...register('address', {
                                setValueAs: (value) => (typeof value === 'string' ? value.trim() : value),
                            })} />
                            {errors.address ? <p className="text-xs text-destructive">{errors.address.message}</p> : null}
                        </div>

                        <div className="space-y-3">
                            <div>
                                <Label className="text-sm font-medium">Company documents</Label>
                                <p className="text-xs text-slate-400">
                                    Upload incorporation, tax, ESG, or other compliance documents. {DOCUMENT_ACCEPT_LABEL}
                                </p>
                            </div>
                            <div className="space-y-3">
                                {documents.map((document) => (
                                    <div
                                        key={document.id}
                                        className="grid items-end gap-3 rounded-lg border border-white/10 bg-slate-900/40 p-4 md:grid-cols-[240px,1fr,auto]"
                                    >
                                        <div className="space-y-2">
                                            <Label htmlFor={`document-type-${document.id}`}>Document type</Label>
                                            <Select
                                                value={document.type}
                                                onValueChange={(value) => handleDocumentTypeChange(document.id, value as DocumentType)}
                                            >
                                                <SelectTrigger className='border-slate-500 rounded-sm py-4' id={`document-type-${document.id}`}>
                                                    <SelectValue placeholder="Select type" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {documentTypeOptions.map((option) => (
                                                        <SelectItem key={option.value} value={option.value}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor={`document-file-${document.id}`}>Document file</Label>
                                            <div className="relative">
                                                <Input
                                                    id={`document-file-${document.id}`}
                                                    className="peer h-12 cursor-pointer border-slate-500 bg-slate-900/40 text-slate-200 file:mr-4 file:cursor-pointer file:rounded-sm file:border-0 file:bg-brand-primary file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-brand-primary/90"
                                                    type="file"
                                                    accept={DOCUMENT_INPUT_ACCEPT}
                                                    onChange={(event) =>
                                                        handleDocumentFileChange(document.id, event.target.files?.[0] ?? null)
                                                    }
                                                />
                                                <p className="mt-1 text-xs text-slate-400">
                                                    PDF, DOCX, or image files. Max size per document applies.
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex justify-end">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                aria-label="Remove document"
                                                onClick={() => handleRemoveDocument(document.id)}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            {documentsError ? (
                                <p className="text-xs text-destructive">{documentsError}</p>
                            ) : (
                                <p className="text-xs text-slate-400">
                                    Submit at least one document so the Elements Supply team can verify your company.
                                </p>
                            )}
                            <Button
                                type="button"
                                className='text-black'
                                variant="outline"
                                onClick={handleAddDocument}
                                disabled={documents.length >= MAX_DOCUMENTS}
                            >
                                {documents.length >= MAX_DOCUMENTS ? 'Document limit reached' : 'Add another document'}
                            </Button>
                        </div>

                        {errorMessage ? (
                            <Alert variant="destructive">
                                <AlertDescription>{errorMessage}</AlertDescription>
                            </Alert>
                        ) : null}

                        <Button type="submit" className="w-full" disabled={isSubmitting}>
                            {isSubmitting ? 'Creating workspace…' : 'Create workspace'}
                        </Button>
                    </form>
                </CardContent>
                <CardFooter className="justify-center text-sm text-slate-400">
                    <span>
                        Already have a workspace?{' '}
                        <Link to="/login" className="font-medium text-brand-primary hover:underline">
                            Sign in
                        </Link>
                    </span>
                </CardFooter>
            </Card>
        </div>
    );
}
