import { Helmet } from 'react-helmet-async';
import { Factory } from 'lucide-react';

import { SupplierApplicationPanel } from '@/pages/settings/supplier-application-panel';

export function SupplierCompanyProfilePage() {
    return (
        <div className="space-y-6">
            <Helmet>
                <title>Supplier Company Profile</title>
            </Helmet>
            <header className="space-y-2">
                <div className="flex items-center gap-3 text-muted-foreground">
                    <Factory className="h-5 w-5" />
                    <span className="text-xs uppercase tracking-wide">Supplier workspace</span>
                </div>
                <div>
                    <h1 className="text-2xl font-semibold text-foreground">Company profile</h1>
                    <p className="text-sm text-muted-foreground">
                        Manage your supplier listing, certifications, and directory visibility. Updates go live for buyers immediately.
                    </p>
                </div>
            </header>
            <SupplierApplicationPanel />
        </div>
    );
}
