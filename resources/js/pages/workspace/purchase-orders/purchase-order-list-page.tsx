import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { usePageTitle } from '@/hooks/use-page-title';

export function PurchaseOrderListPage() {
    usePageTitle('Purchase orders');

    // TODO: clarify with spec how PO change orders, fulfillment metrics, and filtering should display.

    return (
        <section className="space-y-6">
            <header>
                <h1 className="text-3xl font-semibold tracking-tight">Purchase orders</h1>
                <p className="text-sm text-muted-foreground">
                    Monitor issued purchase orders, fulfillment status, and supplier commitments.
                </p>
            </header>

            <Card>
                <CardHeader>
                    <CardTitle>Purchase order list placeholder</CardTitle>
                    <CardDescription>Data grid wiring pending API integration.</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-3">
                    {Array.from({ length: 8 }).map((_, index) => (
                        <Skeleton key={index} className="h-12" />
                    ))}
                </CardContent>
            </Card>
        </section>
    );
}
