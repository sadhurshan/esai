import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Branding } from '@/config/branding';
import { Compass } from 'lucide-react';
import { Helmet } from 'react-helmet-async';
import { useNavigate } from 'react-router-dom';

export function NotFoundPage() {
    const navigate = useNavigate();

    return (
        <section className="mx-auto flex w-full max-w-3xl flex-col gap-6 py-10">
            <Helmet>
                <title>Page Not Found • {Branding.name}</title>
            </Helmet>
            <EmptyState
                title="We could not find that page"
                description="The page you are looking for might have been moved or is temporarily unavailable."
                icon={<Compass className="h-12 w-12" />}
            />
            <div className="flex justify-center gap-3">
                <Button variant="default" onClick={() => navigate('/app')}>
                    Go to dashboard
                </Button>
                <Button variant="outline" onClick={() => navigate(-1)}>
                    Go back
                </Button>
            </div>
        </section>
    );
}
