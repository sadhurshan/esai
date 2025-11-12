import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { usePageTitle } from '@/hooks/use-page-title';

export function AnalyticsPage() {
    usePageTitle('Analytics');

    // TODO: clarify with spec which analytics dashboards and KPIs should be included for v1.

    return (
        <section className="space-y-6">
            <header>
                <h1 className="text-3xl font-semibold tracking-tight">Analytics</h1>
                <p className="text-sm text-muted-foreground">
                    Cross-module insights, KPIs, and trend analysis for sourcing performance.
                </p>
            </header>

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {Array.from({ length: 6 }).map((_, index) => (
                    <Card key={index}>
                        <CardHeader>
                            <CardTitle>Dashboard card {index + 1}</CardTitle>
                            <CardDescription>Visualization placeholder</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Skeleton className="h-24" />
                        </CardContent>
                    </Card>
                ))}
            </div>
        </section>
    );
}
