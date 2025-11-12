import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { usePageTitle } from '@/hooks/use-page-title';

export function InvoiceListPage() {
    usePageTitle('Invoices');

    // TODO: clarify with spec how three-way match state, due dates, and payment progress should display.

    return (
        <section className="space-y-6">
            <header>
                <h1 className="text-3xl font-semibold tracking-tight">Invoices</h1>
                <p className="text-sm text-muted-foreground">
                    Track invoice approvals, match status, and payment readiness.
                </p>
            </header>

            <Card>
                <CardHeader>
                    <CardTitle>Invoices table placeholder</CardTitle>
                    <CardDescription>Invoice data will appear once the invoicing API is integrated.</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-3">
                    {Array.from({ length: 6 }).map((_, index) => (
                        <Skeleton key={index} className="h-12" />
                    ))}
                </CardContent>
            </Card>
        </section>
    );
}
