import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { usePageTitle } from '@/hooks/use-page-title';

export function AdminOverviewPage() {
    usePageTitle('Admin console');

    // TODO: clarify with spec which tenant admin controls (user management, RBAC, plan gating) should ship for v1.

    return (
        <section className="space-y-6">
            <header>
                <h1 className="text-3xl font-semibold tracking-tight">Admin console</h1>
                <p className="text-sm text-muted-foreground">
                    Manage users, roles, billing plans, and platform integrations.
                </p>
            </header>

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {Array.from({ length: 6 }).map((_, index) => (
                    <Card key={index}>
                        <CardHeader>
                            <CardTitle>Admin widget {index + 1}</CardTitle>
                            <CardDescription>Placeholder until implementation</CardDescription>
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
