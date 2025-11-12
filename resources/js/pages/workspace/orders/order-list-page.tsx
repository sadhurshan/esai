import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { usePageTitle } from '@/hooks/use-page-title';

export function OrderListPage() {
    usePageTitle('Orders');

    // TODO: clarify with spec how production orders, lead times, and shipment status should be summarized.

    return (
        <section className="space-y-6">
            <header>
                <h1 className="text-3xl font-semibold tracking-tight">Orders</h1>
                <p className="text-sm text-muted-foreground">
                    Aggregate view of order status, fulfillment progress, and logistics milestones.
                </p>
            </header>

            <Card>
                <CardHeader>
                    <CardTitle>Orders board placeholder</CardTitle>
                    <CardDescription>Kanban or table layout TBD by spec.</CardDescription>
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
