import { ModulePlaceholder } from '@/pages/shared/module-placeholder';
import { Wallet } from 'lucide-react';

export function InvoiceListPage() {
    return (
        <ModulePlaceholder
            moduleKey="invoices"
            title="Invoices"
            description="Three-way match status, approvals, and payment readiness will surface here when the invoices module ships."
            hero={<Wallet className="h-12 w-12" />}
        />
    );
}
