import { ModulePlaceholder } from '@/pages/shared/module-placeholder';
import { NotebookPen } from 'lucide-react';

export function RfqDetailPage() {
    return (
        <ModulePlaceholder
            moduleKey="rfq-detail"
            title="RFQ detail"
            description="Line items, supplier responses, and revision history will render here according to the RFQ deep spec."
            hero={<NotebookPen className="h-12 w-12" />}
        />
    );
}
