import { Link } from 'react-router-dom';
import { Building2, Globe, Hash } from 'lucide-react';
import type { ComponentType } from 'react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { SupplierApplicationPanel } from './supplier-application-panel';

interface SettingsCard {
    title: string;
    description: string;
    to: string;
    icon: ComponentType<{ className?: string }>;
}

const SETTINGS_CARDS: SettingsCard[] = [
    {
        title: 'Company profile',
        description: 'Legal entity details, billing + ship-from addresses, and brand assets.',
        to: '/app/settings/company',
        icon: Building2,
    },
    {
        title: 'Localization & units',
        description: 'Timezones, locale formats, base currencies, and unit-of-measure mappings.',
        to: '/app/settings/localization',
        icon: Globe,
    },
    {
        title: 'Document numbering',
        description: 'Prefixes, padding, and reset cadences for RFQs, POs, invoices, and credits.',
        to: '/app/settings/numbering',
        icon: Hash,
    },
];

export function SettingsPage() {
    return (
        <div className="space-y-8">
            <div>
                <p className="text-sm text-muted-foreground">Workspace</p>
                <h1 className="text-2xl font-semibold tracking-tight">Settings</h1>
                <p className="text-sm text-muted-foreground">
                    Manage branding, localization, numbering, and supplier experience from a single place.
                </p>
            </div>
            <SupplierApplicationPanel />
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {SETTINGS_CARDS.map((card) => {
                    const Icon = card.icon;
                    return (
                        <Card key={card.to} className="flex h-full flex-col justify-between">
                            <CardHeader>
                                <div className="flex items-center gap-3">
                                    <Icon className="h-5 w-5 text-muted-foreground" />
                                    <div>
                                        <CardTitle className="text-lg">{card.title}</CardTitle>
                                        <CardDescription>{card.description}</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="flex w-full justify-end pt-0">
                                <Button asChild variant="outline">
                                    <Link to={card.to} className="inline-flex items-center gap-2">
                                        Manage
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    );
                })}
            </div>
        </div>
    );
}
