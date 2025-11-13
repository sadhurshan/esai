import { ModulePlaceholder } from '@/pages/shared/module-placeholder';
import { FileText } from 'lucide-react';

export function QuoteListPage() {
    return (
        <ModulePlaceholder
            moduleKey="quotes"
            title="Quotes"
            description="Supplier quote comparisons, revision workflows, and acceptance flows will live here per the quoting module spec."
            hero={<FileText className="h-12 w-12" />}
        />
    );
}
