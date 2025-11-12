import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePageTitle } from '@/hooks/use-page-title';
import { useParams } from 'react-router-dom';

export function RfqDetailPage() {
    const { rfqId } = useParams();
    usePageTitle(`RFQ ${rfqId ?? ''}`);

    // TODO: clarify with spec how RFQ overview, items, clarifications, documents, and audit tabs should be structured.

    return (
        <section className="space-y-6">
            <header className="flex flex-col gap-1">
                <h1 className="text-3xl font-semibold tracking-tight">RFQ {rfqId}</h1>
                <p className="text-sm text-muted-foreground">
                    Detailed activity, items, supplier engagement, and documentation for this request.
                </p>
            </header>

            <Tabs defaultValue="overview">
                <TabsList>
                    <TabsTrigger value="overview">Overview</TabsTrigger>
                    <TabsTrigger value="items">Items</TabsTrigger>
                    <TabsTrigger value="clarifications">Clarifications</TabsTrigger>
                    <TabsTrigger value="documents">Documents</TabsTrigger>
                    <TabsTrigger value="audit">Audit trail</TabsTrigger>
                </TabsList>
                <TabsContent value="overview">
                    <Card>
                        <CardHeader>
                            <CardTitle>Overview</CardTitle>
                            <CardDescription>Timeline and metadata placeholders until API wiring is complete.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            <PlaceholderRow label="Status" />
                            <PlaceholderRow label="Due date" />
                            <PlaceholderRow label="Method" />
                            <PlaceholderRow label="Material" />
                        </CardContent>
                    </Card>
                </TabsContent>
                <TabsContent value="items">
                    <Card>
                        <CardHeader>
                            <CardTitle>Items</CardTitle>
                            <CardDescription>Line item table placeholder.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            {Array.from({ length: 5 }).map((_, index) => (
                                <PlaceholderRow key={index} label={`Line item ${index + 1}`} />
                            ))}
                        </CardContent>
                    </Card>
                </TabsContent>
                <TabsContent value="clarifications">
                    <Card>
                        <CardHeader>
                            <CardTitle>Clarifications</CardTitle>
                            <CardDescription>Questions and responses between buyer and suppliers.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <PlaceholderTimeline entries={3} />
                        </CardContent>
                    </Card>
                </TabsContent>
                <TabsContent value="documents">
                    <Card>
                        <CardHeader>
                            <CardTitle>Documents</CardTitle>
                            <CardDescription>Attachment placeholders pending documents integration.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <PlaceholderTimeline entries={4} />
                        </CardContent>
                    </Card>
                </TabsContent>
                <TabsContent value="audit">
                    <Card>
                        <CardHeader>
                            <CardTitle>Audit</CardTitle>
                            <CardDescription>Event trail will render here once audit log API is available.</CardDescription>
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
