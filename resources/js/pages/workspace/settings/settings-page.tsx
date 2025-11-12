import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePageTitle } from '@/hooks/use-page-title';

export function SettingsPage() {
    usePageTitle('Settings');

    // TODO: clarify with spec how settings tabs (profile, company, billing, API keys) should be populated.

    return (
        <section className="space-y-6">
            <header>
                <h1 className="text-3xl font-semibold tracking-tight">Workspace settings</h1>
                <p className="text-sm text-muted-foreground">
                    Manage organization preferences, billing, and integrations.
                </p>
            </header>

            <Tabs defaultValue="profile">
                <TabsList>
                    <TabsTrigger value="profile">Profile</TabsTrigger>
                    <TabsTrigger value="company">Company</TabsTrigger>
                    <TabsTrigger value="billing">Billing</TabsTrigger>
                    <TabsTrigger value="api">API keys</TabsTrigger>
                </TabsList>

                <TabsContent value="profile">
                    <SettingsPlaceholder title="Profile" description="User settings coming soon." />
                </TabsContent>
                <TabsContent value="company">
                    <SettingsPlaceholder title="Company" description="Company configuration placeholder." />
                </TabsContent>
                <TabsContent value="billing">
                    <SettingsPlaceholder title="Billing" description="Plan management and invoices." />
                </TabsContent>
                <TabsContent value="api">
                    <SettingsPlaceholder title="API access" description="Token management UI pending." />
                </TabsContent>
            </Tabs>
        </section>
    );
}

function SettingsPlaceholder({ title, description }: { title: string; description: string }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent className="text-sm text-muted-foreground">
                Configuration inputs will appear here once requirements are finalized.
            </CardContent>
        </Card>
    );
}
