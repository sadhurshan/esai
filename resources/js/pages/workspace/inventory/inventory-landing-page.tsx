import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { usePageTitle } from '@/hooks/use-page-title';

export function InventoryLandingPage() {
    usePageTitle('Inventory');

    // TODO: clarify with spec how inventory snapshots, valuation, and reorder alerts should appear.

    return (
        <section className="space-y-6">
            <header>
                <h1 className="text-3xl font-semibold tracking-tight">Inventory</h1>
                <p className="text-sm text-muted-foreground">
                    Monitor stock positions, reorder signals, and shortages across sites.
                </p>
            </header>

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {Array.from({ length: 6 }).map((_, index) => (
                    <Card key={index}>
                        <CardHeader>
                            <CardTitle>Inventory widget {index + 1}</CardTitle>
                            <CardDescription>Placeholder metric</CardDescription>
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
