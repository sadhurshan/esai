import { ModulePlaceholder } from '@/pages/shared/module-placeholder';
import { Settings } from 'lucide-react';

export function SettingsPage() {
    return (
        <ModulePlaceholder
            moduleKey="settings"
            title="Workspace settings"
            description="Profile, billing, and workspace configuration screens will render here."
            hero={<Settings className="h-12 w-12" />}
        />
    );
}
