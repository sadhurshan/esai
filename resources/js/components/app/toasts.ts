import { publishToast } from '@/components/ui/use-toast';

export function successToast(message: string, description?: string) {
    publishToast({ title: message, description, variant: 'success' });
}

export function errorToast(message: string, description?: string) {
    publishToast({ title: message, description, variant: 'destructive' });
}
