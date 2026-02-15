import {
    Bell,
    Building2,
    CreditCard,
    Globe,
    Hash,
    ShieldQuestion,
    UserPlus2,
    UserRound,
    Users2,
} from 'lucide-react';
import { useEffect, type ComponentType } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';

import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useAuth } from '@/contexts/auth-context';
import { SupplierApplicationPanel } from './supplier-application-panel';

interface SettingsCard {
    title: string;
    description: string;
    to: string;
    icon: ComponentType<{ className?: string }>;
}

const SETTINGS_CARDS: SettingsCard[] = [
    {
        title: 'Personal profile',
        description:
            'Manage your name, avatar, contact info, and locale preferences.',
        to: '/app/settings/profile',
        icon: UserRound,
    },
    {
        title: 'Billing & plan',
        description: 'Upgrade tiers, review usage, and manage payment status.',
        to: '/app/settings/billing',
        icon: CreditCard,
    },
    {
        title: 'Notification preferences',
        description:
            'Choose which events trigger email, push, or digest alerts for your user.',
        to: '/app/settings/notifications',
        icon: Bell,
    },
    {
        title: 'Company profile',
        description:
            'Legal entity details, billing + ship-from addresses, and brand assets.',
        to: '/app/settings/company',
        icon: Building2,
    },
    {
        title: 'Team invitations',
        description:
            'Invite buyers, suppliers, and finance collaborators with role-scoped access.',
        to: '/app/settings/invitations',
        icon: UserPlus2,
    },
    {
        title: 'Team roster',
        description:
            'Review existing members, edit roles, and remove workspace access.',
        to: '/app/settings/team',
        icon: Users2,
    },
    {
        title: 'Role definitions',
        description:
            'View each seeded role template and the permissions it grants.',
        to: '/app/settings/roles',
        icon: ShieldQuestion,
    },
    {
        title: 'Localization & units',
        description:
            'Timezones, locale formats, base currencies, and unit-of-measure mappings.',
        to: '/app/settings/localization',
        icon: Globe,
    },
    {
        title: 'Document numbering',
        description:
            'Prefixes, padding, and reset cadences for RFQs, POs, invoices, and credits.',
        to: '/app/settings/numbering',
        icon: Hash,
    },
];

export function SettingsPage() {
    const navigate = useNavigate();
    const { state } = useAuth();
    const [searchParams] = useSearchParams();

    useEffect(() => {
        if (searchParams.get('tab') === 'billing') {
            navigate('/app/settings/billing', { replace: true });
        }
    }, [navigate, searchParams]);

    const supplierStatus = state.company?.supplier_status ?? null;
    const isSupplierStart =
        state.company?.start_mode === 'supplier' ||
        (supplierStatus && supplierStatus !== 'none');
    const visibleCards = isSupplierStart
        ? SETTINGS_CARDS.filter((card) => card.to !== '/app/settings/billing')
        : SETTINGS_CARDS;

    return (
        <div className="space-y-8">
            <div>
                <p className="text-sm text-muted-foreground">Workspace</p>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Settings
                </h1>
                <p className="text-sm text-muted-foreground">
                    Manage branding, localization, numbering, and supplier
                    experience from a single place.
                </p>
            </div>
            <SupplierApplicationPanel />
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {visibleCards.map((card) => {
                    const Icon = card.icon;
                    return (
                        <Card
                            key={card.to}
                            className="flex h-full flex-col justify-between"
                        >
                            <CardHeader>
                                <div className="flex items-center gap-3">
                                    <Icon className="h-5 w-5 text-muted-foreground" />
                                    <div>
                                        <CardTitle className="text-lg">
                                            {card.title}
                                        </CardTitle>
                                        <CardDescription>
                                            {card.description}
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="flex w-full justify-end pt-0">
                                <Button asChild variant="outline">
                                    <Link
                                        to={card.to}
                                        className="inline-flex items-center gap-2"
                                    >
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
