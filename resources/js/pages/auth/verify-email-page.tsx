import { Branding } from '@/config/branding';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Helmet } from 'react-helmet-async';
import { MailCheck } from 'lucide-react';
import { useNavigate } from 'react-router-dom';

export function VerifyEmailPage() {
    const navigate = useNavigate();

    return (
        <div className="flex min-h-screen items-center justify-center bg-muted/40 px-4 py-12">
            <Helmet>
                <title>Verify your email • {Branding.name}</title>
            </Helmet>
            <Card className="w-full max-w-md text-center shadow-lg">
                <CardHeader className="items-center">
                    <MailCheck className="h-10 w-10 text-brand-primary" />
                    <CardTitle className="mt-2 text-2xl font-semibold text-foreground">Check your email</CardTitle>
                    <CardDescription>
                        We sent a verification link to your inbox. Follow the instructions to activate your Elements Supply account.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex justify-center">
                    <Button variant="outline" onClick={() => navigate('/login')}>
                        Return to sign in
                    </Button>
                </CardContent>
            </Card>
        </div>
    );
}
