import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { usePageTitle } from '@/hooks/use-page-title';

export function DashboardPage() {
    usePageTitle('Dashboard');

    // TODO: clarify with spec how the dashboard metrics and timeline widgets should be composed per `/deep-specs/*`.

    return (
        <section className="space-y-6">
            <header>
                <h1 className="text-3xl font-semibold tracking-tight">Operations overview</h1>
                <p className="text-sm text-muted-foreground">
                    Snapshot of sourcing, orders, and supplier health for your workspace.
                </p>
            </header>

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                {Array.from({ length: 4 }).map((_, index) => (
                    <Card key={index}>
                        <CardHeader>
                            <CardTitle>Metric placeholder</CardTitle>
                            <CardDescription>Coming soon</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Skeleton className="h-16" />
                        </CardContent>
                    </Card>
                ))}
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Recent activity</CardTitle>
                    <CardDescription>Transitions and events across procurement modules.</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-3">
                    {Array.from({ length: 5 }).map((_, index) => (
                        <Skeleton key={index} className="h-12" />
                    ))}
                </CardContent>
            </Card>
        </section>
    );
}
