import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { usePageTitle } from '@/hooks/use-page-title';

export function QuoteListPage() {
    usePageTitle('Quotes');

    // TODO: clarify with spec how quote list filters, status badges, and revision markers should render.

    return (
        <section className="space-y-6">
            <header>
                <h1 className="text-3xl font-semibold tracking-tight">Quotes</h1>
                <p className="text-sm text-muted-foreground">
                    Track supplier submissions, revision history, and award readiness.
                </p>
            </header>

            <Card>
                <CardHeader>
                    <CardTitle>Quotes table placeholder</CardTitle>
                    <CardDescription>Results will appear once the quotes API is wired.</CardDescription>
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
