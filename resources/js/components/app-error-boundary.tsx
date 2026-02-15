import { Button } from '@/components/ui/button';
import { Branding } from '@/config/branding';
import { Component, type ErrorInfo, type ReactNode } from 'react';
import { Helmet } from 'react-helmet-async';

interface AppErrorBoundaryProps {
    children: ReactNode;
}

interface AppErrorBoundaryState {
    hasError: boolean;
    error?: Error;
}

export class AppErrorBoundary extends Component<
    AppErrorBoundaryProps,
    AppErrorBoundaryState
> {
    override state: AppErrorBoundaryState = {
        hasError: false,
        error: undefined,
    };

    static getDerivedStateFromError(error: Error): AppErrorBoundaryState {
        return {
            hasError: true,
            error,
        };
    }

    override componentDidCatch(error: Error, info: ErrorInfo) {
        console.error('Uncaught error in application boundary', error, info);
    }

    private handleReload = () => {
        window.location.replace('/app');
    };

    override render(): ReactNode {
        if (!this.state.hasError) {
            return this.props.children;
        }

        return (
            <div className="flex min-h-screen flex-col items-center justify-center bg-muted/40 p-6">
                <Helmet>
                    <title>Unexpected Error • {Branding.name}</title>
                </Helmet>
                <div className="w-full max-w-md rounded-xl border border-border bg-background p-8 text-center shadow-sm">
                    <h1 className="text-2xl font-semibold text-foreground">
                        Something went wrong
                    </h1>
                    <p className="mt-3 text-sm text-muted-foreground">
                        An unexpected error occurred while rendering this page.
                        Please try reloading, or reach out to support if the
                        problem continues.
                    </p>
                    {this.state.error?.message ? (
                        <p
                            className="mt-4 truncate text-xs text-muted-foreground"
                            title={this.state.error.message}
                        >
                            {this.state.error.message}
                        </p>
                    ) : null}
                    <div className="mt-6 flex justify-center">
                        <Button variant="outline" onClick={this.handleReload}>
                            Reload dashboard
                        </Button>
                    </div>
                </div>
            </div>
        );
    }
}
