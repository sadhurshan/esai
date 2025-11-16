import * as React from 'react';
import { Slot } from '@radix-ui/react-slot';
import {
    Controller,
    FormProvider,
    useFormContext,
    type ControllerProps,
    type FieldPath,
    type FieldValues,
} from 'react-hook-form';

import { cn } from '@/lib/utils';

const Form = FormProvider;

interface FormFieldContextValue<
    TFieldValues extends FieldValues = FieldValues,
    TName extends FieldPath<TFieldValues> = FieldPath<TFieldValues>,
> {
    name: TName;
}

const FormFieldContext = React.createContext<FormFieldContextValue>({} as FormFieldContextValue);

const FormItemContext = React.createContext<{ id: string } | undefined>(undefined);

export function useFormField() {
    const fieldContext = React.useContext(FormFieldContext);
    const itemContext = React.useContext(FormItemContext);
    const formContext = useFormContext();

    if (!fieldContext) {
        throw new Error('useFormField should be used within <FormField>');
    }

    const fieldState = formContext.getFieldState(fieldContext.name, formContext.formState);

    return {
        id: itemContext?.id,
        name: fieldContext.name,
        formItemId: itemContext?.id,
        formDescriptionId: itemContext && `${itemContext.id}-description`,
        formMessageId: itemContext && `${itemContext.id}-message`,
        ...fieldState,
    };
}

export type FormFieldProps<
    TFieldValues extends FieldValues = FieldValues,
    TName extends FieldPath<TFieldValues> = FieldPath<TFieldValues>,
> = ControllerProps<TFieldValues, TName>;

export function FormField<
    TFieldValues extends FieldValues = FieldValues,
    TName extends FieldPath<TFieldValues> = FieldPath<TFieldValues>,
>({ name, ...props }: FormFieldProps<TFieldValues, TName>) {
    return (
        <FormFieldContext.Provider value={{ name }}>
            <Controller name={name} {...props} />
        </FormFieldContext.Provider>
    );
}

export type FormItemProps = React.HTMLAttributes<HTMLDivElement>;

export function FormItem({ className, ...props }: FormItemProps) {
    const id = React.useId();

    return (
        <FormItemContext.Provider value={{ id }}>
            <div className={cn('space-y-2', className)} {...props} />
        </FormItemContext.Provider>
    );
}

export type FormLabelProps = React.LabelHTMLAttributes<HTMLLabelElement>;

export function FormLabel({ className, ...props }: FormLabelProps) {
    const { formItemId, error } = useFormField();

    return <label className={cn(error && 'text-destructive', className)} htmlFor={formItemId} {...props} />;
}

export type FormControlProps = React.ComponentPropsWithoutRef<typeof Slot>;

export function FormControl({ ...props }: FormControlProps) {
    const { formItemId, formDescriptionId, formMessageId } = useFormField();

    return <Slot id={formItemId} aria-describedby={cn(formDescriptionId, formMessageId)} {...props} />;
}

export type FormDescriptionProps = React.HTMLAttributes<HTMLParagraphElement>;

export function FormDescription({ className, ...props }: FormDescriptionProps) {
    const { formDescriptionId } = useFormField();

    return <p id={formDescriptionId} className={cn('text-sm text-muted-foreground', className)} {...props} />;
}

export type FormMessageProps = React.HTMLAttributes<HTMLParagraphElement>;

export function FormMessage({ className, children, ...props }: FormMessageProps) {
    const { error, formMessageId } = useFormField();
    const body = error ? String(error.message ?? children) : children;

    if (!body) {
        return null;
    }

    return (
        <p id={formMessageId} className={cn('text-sm font-medium text-destructive', className)} {...props}>
            {body}
        </p>
    );
}

export { Form };
