import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePageTitle } from '@/hooks/use-page-title';
import { useParams } from 'react-router-dom';

export function PurchaseOrderDetailPage() {
    const { purchaseOrderId } = useParams();
    usePageTitle(`Purchase order ${purchaseOrderId ?? ''}`);

    // TODO: clarify with spec how PO lifecycle, revision history, and receiving integration should render.

    return (
        <section className="space-y-6">
            <header className="flex flex-col gap-1">
                <h1 className="text-3xl font-semibold tracking-tight">Purchase order {purchaseOrderId}</h1>
                <p className="text-sm text-muted-foreground">
                    Fulfillment details, change orders, receipts, and invoices will surface here.
                </p>
            </header>

            <Tabs defaultValue="summary">
                <TabsList>
                    <TabsTrigger value="summary">Summary</TabsTrigger>
                    <TabsTrigger value="line-items">Line items</TabsTrigger>
                    <TabsTrigger value="receiving">Receiving</TabsTrigger>
                    <TabsTrigger value="invoices">Invoices</TabsTrigger>
                    <TabsTrigger value="audit">Audit trail</TabsTrigger>
                </TabsList>

                <TabsContent value="summary">
                    <Card>
                        <CardHeader>
                            <CardTitle>Order summary</CardTitle>
                            <CardDescription>Snapshot of supplier, totals, and key milestones.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            <PlaceholderRow label="Supplier" />
                            <PlaceholderRow label="Status" />
                            <PlaceholderRow label="Issued date" />
                            <PlaceholderRow label="Promised ship" />
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="line-items">
                    <Card>
                        <CardHeader>
                            <CardTitle>Line items</CardTitle>
                            <CardDescription>Tabular detail of ordered parts and quantities.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-2">
                            {Array.from({ length: 5 }).map((_, index) => (
                                <PlaceholderRow key={index} label={`Item ${index + 1}`} />
                            ))}
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="receiving">
                    <Card>
                        <CardHeader>
                            <CardTitle>Receiving</CardTitle>
                            <CardDescription>Receiving and inspection timeline placeholder.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <PlaceholderTimeline entries={4} />
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="invoices">
                    <Card>
                        <CardHeader>
                            <CardTitle>Invoices</CardTitle>
                            <CardDescription>Matching status pending invoicing module wiring.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <PlaceholderTimeline entries={3} />
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="audit">
                    <Card>
                        <CardHeader>
                            <CardTitle>Audit trail</CardTitle>
                            <CardDescription>Track every change once audit log endpoints are connected.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <PlaceholderTimeline entries={5} />
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </section>
    );
}

function PlaceholderRow({ label }: { label: string }) {
    return (
        <div className="flex items-center justify-between rounded-lg border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
            <span className="font-medium text-foreground">{label}</span>
            <span>Pending data</span>
        </div>
    );
}

function PlaceholderTimeline({ entries }: { entries: number }) {
    return (
        <ol className="space-y-3 text-sm text-muted-foreground">
            {Array.from({ length: entries }).map((_, index) => (
                <li key={index} className="rounded-lg border bg-muted/40 px-3 py-2">
                    Timeline event placeholder #{index + 1}
                </li>
            ))}
        </ol>
    );
}
