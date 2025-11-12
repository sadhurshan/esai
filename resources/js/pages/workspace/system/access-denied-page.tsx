import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { usePageTitle } from '@/hooks/use-page-title';
import { ShieldAlert } from 'lucide-react';
import { useNavigate } from 'react-router-dom';

export function AccessDeniedPage() {
    usePageTitle('Access denied');
    const navigate = useNavigate();

    return (
        <div className="flex min-h-screen items-center justify-center bg-muted/40 px-4 py-12">
            <Card className="w-full max-w-lg text-center">
                <CardHeader className="items-center">
                    <ShieldAlert className="mb-3 h-10 w-10 text-destructive" />
                    <CardTitle className="text-2xl font-semibold">Access denied</CardTitle>
                    <CardDescription>
                        You do not have permission to view this workspace area. Contact an administrator if you
                        believe this is an error.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-3">
                    <Button onClick={() => navigate(-1)}>Go back</Button>
                    <Button variant="outline" onClick={() => navigate('/app')}>
                        View dashboard
                    </Button>
                </CardContent>
            </Card>
        </div>
    );
}
