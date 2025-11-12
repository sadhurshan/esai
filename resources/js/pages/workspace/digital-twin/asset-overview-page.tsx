import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { usePageTitle } from '@/hooks/use-page-title';

export function AssetOverviewPage() {
    usePageTitle('Assets');

    // TODO: clarify with spec how digital twin asset state, telemetry, and maintenance alerts should render.

    return (
        <section className="space-y-6">
            <header>
                <h1 className="text-3xl font-semibold tracking-tight">Digital twin</h1>
                <p className="text-sm text-muted-foreground">
                    Surface machine health, runtime metrics, and maintenance triggers.
                </p>
            </header>

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {Array.from({ length: 6 }).map((_, index) => (
                    <Card key={index}>
                        <CardHeader>
                            <CardTitle>Asset card {index + 1}</CardTitle>
                            <CardDescription>Awaiting telemetry integration</CardDescription>
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
