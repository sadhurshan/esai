import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { usePageTitle } from '@/hooks/use-page-title';
import { FileSearch } from 'lucide-react';
import { useNavigate } from 'react-router-dom';

export function NotFoundPage() {
    usePageTitle('Not found');
    const navigate = useNavigate();

    return (
        <div className="flex min-h-screen items-center justify-center bg-muted/40 px-4 py-12">
            <Card className="w-full max-w-lg text-center">
                <CardHeader className="items-center">
                    <FileSearch className="mb-3 h-10 w-10 text-muted-foreground" />
                    <CardTitle className="text-2xl font-semibold">Page not found</CardTitle>
                    <CardDescription>
                        The page you were looking for is unavailable, moved, or does not exist.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-3">
                    <Button onClick={() => navigate(-1)}>Go back</Button>
                    <Button variant="outline" onClick={() => navigate('/app')}>
                        Return to dashboard
                    </Button>
                </CardContent>
            </Card>
        </div>
    );
}
