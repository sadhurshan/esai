import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { usePageTitle } from '@/hooks/use-page-title';

export function RiskEsgPage() {
    usePageTitle('Risk & ESG');

    // TODO: clarify with spec how supplier risk scoring, ESG metrics, and alerts should render.

    return (
        <section className="space-y-6">
            <header>
                <h1 className="text-3xl font-semibold tracking-tight">Risk & ESG</h1>
                <p className="text-sm text-muted-foreground">
                    Monitor supplier performance, compliance posture, and sustainability signals.
                </p>
            </header>

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {Array.from({ length: 6 }).map((_, index) => (
                    <Card key={index}>
                        <CardHeader>
                            <CardTitle>Risk widget {index + 1}</CardTitle>
                            <CardDescription>Awaiting data integration</CardDescription>
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
