import { ModulePlaceholder } from '@/pages/shared/module-placeholder';
import { ShieldAlert } from 'lucide-react';

export function RiskPage() {
    return (
        <ModulePlaceholder
            moduleKey="risk"
            title="Risk & ESG"
            description="Third-party risk, ESG scoring, and monitoring feeds will integrate here based on the risk module specification."
            hero={<ShieldAlert className="h-12 w-12" />}
        />
    );
}
