import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { home, rfq, suppliers } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Home',
        href: home().url,
    },
];

const howItWorks = [
    {
        title: '1. Submit Requirements',
        description:
            'Capture CAD files, part specs, and delivery expectations in minutes.',
    },
    {
        title: '2. Match Verified Suppliers',
        description:
            'Elements Supply AI routes RFQs to qualified suppliers with transparent credentials.',
    },
    {
        title: '3. Review Quotes',
        description:
            'Compare pricing, lead times, quality documentation, and clarifications in one workspace.',
    },
    {
        title: '4. Award & Track Orders',
        description:
            'Convert the winning quote into a PO and follow production, shipping, and receiving milestones.',
    },
];

const solutionSections = [
    {
        title: 'Smart RFQ Form',
        description:
            'Guided intake with CAD-aware validation ensures every RFQ includes critical tolerances, finishes, and delivery details.',
    },
    {
        title: 'Verified Suppliers',
        description:
            'KYC, production certificates, risk scoring, and historical performance data keep sourcing compliant and reliable.',
    },
    {
        title: 'Open RFQs',
        description:
            'Launch open bidding to attract new suppliers while maintaining audit trails and invitation controls.',
    },
    {
        title: 'Quote Tracking',
        description:
            'Monitor revisions, attachments, and AI insights to shortlist the most competitive proposals.',
    },
    {
        title: 'Order Management',
        description:
            'Follow every PO from acknowledgment to delivery with change orders, status updates, and receiving logs.',
    },
    {
        title: 'CAD Uploads',
        description:
            'Secure, watermark-ready storage for STEP, IGES, DWG, DXF, SLDPRT, and STL files with version controls.',
    },
    {
        title: 'Flexible Supplier',
        description:
            'Blend contracted and spot-buy suppliers while enforcing approvals, incoterms, and audit requirements.',
    },
];

export default function Home() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Home" />
            <div className="flex flex-1 flex-col gap-10 px-4 py-8">
                <section className="mx-auto flex w-full max-w-5xl flex-col items-center gap-6 text-center">
                    <span className="rounded-full border border-primary/40 px-4 py-1 text-xs font-semibold uppercase tracking-[0.3em] text-primary/80">
                        Elements Supply AI
                    </span>
                    <h1 className="text-3xl font-bold tracking-tight text-foreground md:text-5xl">
                        Simplified. Verified. Delivered.
                    </h1>
                    <p className="max-w-3xl text-base text-muted-foreground md:text-lg">
                        One sourcing command center to launch CAD-aware RFQs, qualify suppliers, compare quotes, and track orders through receiving and quality checks.
                    </p>
                    <div className="flex flex-wrap items-center justify-center gap-3">
                        <Button asChild size="lg">
                            <Link href={rfq.new()}>Request Quote</Link>
                        </Button>
                        <Button asChild variant="outline" size="lg">
                            <Link href={suppliers.index()}>Browse Suppliers</Link>
                        </Button>
                    </div>
                </section>

                <section className="mx-auto w-full max-w-6xl">
                    <h2 className="text-lg font-semibold uppercase tracking-wide text-muted-foreground">
                        How it Works
                    </h2>
                    <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        {howItWorks.map((step) => (
                            <Card key={step.title} className="border-muted">
                                <CardHeader>
                                    <CardTitle className="text-base font-semibold text-primary">
                                        {step.title}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm text-muted-foreground">
                                        {step.description}
                                    </p>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </section>

                <section className="mx-auto w-full max-w-6xl">
                    <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                        {solutionSections.map((section) => (
                            <Card key={section.title} className="border-muted/70 bg-background/60">
                                <CardHeader>
                                    <CardTitle className="text-lg font-semibold text-foreground">
                                        {section.title}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm text-muted-foreground">
                                        {section.description}
                                    </p>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
