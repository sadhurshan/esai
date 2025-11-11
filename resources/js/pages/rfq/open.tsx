import { FileDropzone } from '@/components/app';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { home } from '@/routes';
import rfqRoutes from '@/routes/rfq';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Home', href: home().url },
    { title: 'RFQ', href: rfqRoutes.index().url },
    { title: 'Open RFQ', href: rfqRoutes.open({ id: 303 }).url },
];

const cadAcceptTypes = [
    '.STEP',
    '.STP',
    '.IGES',
    '.IGS',
    '.DWG',
    '.DXF',
    '.SLDPRT',
    '.3MF',
    '.STL',
    '.PDF',
];

export default function RfqOpen() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Open RFQ" />
            <div className="flex flex-1 flex-col gap-6 px-4 py-6">
                <section className="rounded-xl border border-primary/30 bg-primary/10 p-6 text-primary-foreground shadow-sm backdrop-blur">
                    <p className="text-xs uppercase tracking-[0.35em] text-primary">
                        Open RFQ | Bidding
                    </p>
                    <h1 className="mt-2 text-2xl font-semibold text-foreground">
                        Hydraulic Manifold | Elements Supply AI
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Submit your bid with unit pricing, realistic lead times, and supporting attachments. Buyers will review quotes based on quality, delivery, and commercial fit.
                    </p>
                </section>

                <Card className="border-muted/60">
                    <CardHeader>
                        <CardTitle className="text-lg">Submit Bid</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="space-y-2">
                                <Label htmlFor="unit-price">Unit Price (USD)</Label>
                                <Input
                                    id="unit-price"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    placeholder="Enter price per unit"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="lead-time">Lead Time (days)</Label>
                                <Input id="lead-time" type="number" min={0} placeholder="e.g. 30" />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="bid-valid">Bid Valid Through</Label>
                                <Input id="bid-valid" type="date" />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="bid-note">Note to Buyer</Label>
                            <Textarea
                                id="bid-note"
                                rows={4}
                                placeholder="Outline assumptions, tooling charges, or alternate materials."
                            />
                        </div>

                        <div className="space-y-2">
                            <Label>Attachment</Label>
                            <FileDropzone
                                accept={cadAcceptTypes}
                                description="Include quote PDFs or supplemental specs."
                            />
                        </div>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" type="button">
                                Save Draft
                            </Button>
                            <Button type="button">Submit Bid</Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
