import { CADPreview, FileDropzone } from '@/components/app';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { home, rfq as rfqRoutes } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Home', href: home().url },
    { title: 'RFQ', href: rfqRoutes.index().url },
    { title: 'New RFQ', href: rfqRoutes.new().url },
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

export default function RfqNew() {
    const [activeTab, setActiveTab] = useState('manufacture');
    const [openBidding, setOpenBidding] = useState(false);
    const [selectedCad, setSelectedCad] = useState<File | null>(null);

    const cadMessage = useMemo(() => {
        if (!selectedCad) {
            return 'CAD preview coming soon. Upload a file to enable the viewer.';
        }
        return `${selectedCad.name} uploaded. Preview rendering is mocked for now.`;
    }, [selectedCad]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New RFQ" />
            <div className="flex flex-1 flex-col gap-6 px-4 py-6">
                <header className="space-y-2">
                    <h1 className="text-2xl font-semibold text-foreground">New RFQ</h1>
                    <p className="text-sm text-muted-foreground">
                        Capture detailed manufacturing requirements and share with suppliers or publish as an open bidding opportunity.
                    </p>
                </header>

                <Tabs value={activeTab} defaultValue="manufacture" onValueChange={setActiveTab}>
                    <TabsList>
                        <TabsTrigger value="ready-made">Ready Made</TabsTrigger>
                        <TabsTrigger value="manufacture">Manufacture</TabsTrigger>
                    </TabsList>

                    <TabsContent value="ready-made" className="space-y-6">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="ready-name">Item Name</Label>
                                <Input id="ready-name" placeholder="Standard fastener kit" />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="ready-sku">Catalog SKU</Label>
                                <Input id="ready-sku" placeholder="SKU-12345" />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="ready-quantity">Quantity</Label>
                                <Input id="ready-quantity" type="number" min={1} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="ready-delivery">Required Delivery Timeline</Label>
                                <Input id="ready-delivery" placeholder="Within 2 weeks" />
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="ready-notes">Notes</Label>
                            <Textarea
                                id="ready-notes"
                                rows={4}
                                placeholder="Include packaging requirements or compliance expectations."
                            />
                        </div>
                        {/* TODO: clarify with spec - additional Ready Made fields (pricing bands, supplier preference, etc.) */}
                        <div className="flex justify-end">
                            <Button type="button">Save Ready Made RFQ</Button>
                        </div>
                    </TabsContent>

                    <TabsContent value="manufacture" className="space-y-8">
                        <section className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="part-name">Part Name</Label>
                                <Input id="part-name" placeholder="Precision Valve Body" />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="quantity">Quantity</Label>
                                <Input id="quantity" type="number" min={1} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="timeline">Delivery Timeline</Label>
                                <Input id="timeline" placeholder="6 weeks from award" />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="material">Material</Label>
                                <Input id="material" placeholder="Aluminum 7075-T6" />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="tolerance">Tolerance</Label>
                                <Input id="tolerance" placeholder="±0.005 in" />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="finish">Finish</Label>
                                <Input id="finish" placeholder="Anodized Type II" />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="method">Manufacturing Method</Label>
                                <Input id="method" placeholder="5-axis CNC" />
                            </div>
                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="notes">Notes</Label>
                                <Textarea
                                    id="notes"
                                    rows={4}
                                    placeholder="Add inspection requirements, certifications, or packaging details."
                                />
                            </div>
                        </section>

                        <section className="grid gap-4 md:grid-cols-[1.2fr_1fr]">
                            <div className="space-y-3">
                                <Label>CAD File Upload</Label>
                                <FileDropzone
                                    accept={cadAcceptTypes}
                                    onFilesSelected={(files) =>
                                        setSelectedCad(files.item(0))
                                    }
                                    description="Drag your CAD here or browse supported formats."
                                />
                            </div>
                            <CADPreview fileName={selectedCad?.name} message={cadMessage} />
                        </section>

                        <section className="flex items-start gap-3 rounded-lg border border-muted/60 bg-muted/20 p-4">
                            <Checkbox
                                id="open-bidding"
                                checked={openBidding}
                                onCheckedChange={(checked) =>
                                    setOpenBidding(Boolean(checked))
                                }
                            />
                            <div className="space-y-1">
                                <Label htmlFor="open-bidding" className="text-sm font-medium">
                                    Open RFQ (Bidding)
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    When enabled, verified suppliers can discover and bid on this RFQ. Invitations remain available for priority partners.
                                </p>
                            </div>
                        </section>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" type="button">
                                Save as Draft
                            </Button>
                            <Button type="button">Publish RFQ</Button>
                        </div>
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}
