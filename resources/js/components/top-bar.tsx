import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Check, ChevronDown, Loader2 } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '@/contexts/auth-context';
import { WorkspaceBreadcrumbs } from './breadcrumbs';
import { NotificationBell } from '@/components/notifications/notification-bell';
import { useSwitchCompany, useUserCompanies } from '@/hooks/api/use-user-companies';
import { publishToast } from '@/components/ui/use-toast';
import { ApiError } from '@/lib/api';
import { isPlatformRole } from '@/constants/platform-roles';

function formatPersonaTitle(persona: { type: 'buyer' | 'supplier'; company_name?: string | null; supplier_company_name?: string | null }): string {
    if (persona.type === 'buyer') {
        return `Buyer · ${persona.company_name ?? 'Buyer workspace'}`;
    }

    const supplierName = persona.company_name ?? persona.supplier_company_name ?? 'Supplier workspace';

    return `Supplier · ${supplierName}`;
}

function formatPersonaMeta(persona: {
    type: 'buyer' | 'supplier';
    company_name?: string | null;
    supplier_company_name?: string | null;
}): string | null {
    if (persona.type === 'buyer') {
        return persona.company_name ?? null;
    }

    if (persona.supplier_company_name) {
        return persona.supplier_company_name;
    }

    return null;
}

export function TopBar() {
    const navigate = useNavigate();
    const { state, logout, personas, activePersona, switchPersona } = useAuth();
    const companyName = state.company?.name ?? 'Company';
    const companiesQuery = useUserCompanies();
    const switchCompany = useSwitchCompany();
    const companies = companiesQuery.data ?? [];
    const canSwitchCompanies = companies.length > 1;
    const [isSwitchingPersona, setIsSwitchingPersona] = useState(false);
    const initials = useMemo(() => {
        const source = state.user?.name ?? state.user?.email ?? 'User';
        return source
            .split(' ')
            .map((part) => part.charAt(0))
            .join('')
            .slice(0, 2)
            .toUpperCase();
    }, [state.user?.email, state.user?.name]);
    const isPlatformOperator = isPlatformRole(state.user?.role);
    const isSupplierPersona = activePersona?.type === 'supplier';
    const personaButtonLabel = useMemo(() => {
        if (!activePersona) {
            return 'Default persona';
        }

        return formatPersonaTitle(activePersona);
    }, [activePersona]);
    const personaButtonDescription = useMemo(() => {
        if (!activePersona) {
            return null;
        }

        return formatPersonaMeta(activePersona);
    }, [activePersona]);
    const supplierBadgeLabel = useMemo(() => {
        if (activePersona?.type !== 'supplier') {
            return null;
        }

        const hostBuyerName = activePersona.company_name ?? activePersona.supplier_company_name;
        if (!hostBuyerName) {
            return 'Acting as Supplier';
        }

        return `Acting as Supplier for ${hostBuyerName}`;
    }, [activePersona]);
    const handlePersonaLanding = useCallback(
        (personaType: 'buyer' | 'supplier') => {
            if (personaType === 'supplier') {
                navigate('/app/supplier', { replace: true });
                return;
            }

            navigate('/app', { replace: true });
        },
        [navigate],
    );

    return (
        <header className="flex h-16 items-center gap-4 border-b bg-background px-4">
            <div className="flex flex-1 items-center gap-3">
                <SidebarTrigger className="md:hidden" />
                {/* <WorkspaceBreadcrumbs /> */}
            </div>

            <div className="flex items-center gap-3">
                {personas.length > 0 ? (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="outline"
                                className="relative hidden w-64 flex-col items-start gap-0 text-left pr-8 md:flex"
                            >
                                {/* <span className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    {activePersona?.type === 'supplier' ? 'Supplier mode' : 'Buyer mode'}
                                </span> */}
                                <span className="text-sm font-medium leading-tight">
                                    {personaButtonLabel}
                                </span>

                                {isSwitchingPersona ? (
                                    <Loader2 className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin" />
                                ) : personas.length > 1 ? (
                                    <ChevronDown className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                                ) : null}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-64">
                            <DropdownMenuLabel>Switch persona</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            {personas.map((persona) => (
                                <DropdownMenuItem
                                    key={persona.key}
                                    disabled={
                                        persona.key === activePersona?.key || isSwitchingPersona || personas.length <= 1
                                    }
                                    onSelect={(event) => {
                                        event.preventDefault();

                                        if (persona.key === activePersona?.key || isSwitchingPersona) {
                                            return;
                                        }

                                        setIsSwitchingPersona(true);

                                        void switchPersona(persona.key)
                                            .then(() => {
                                                publishToast({
                                                    variant: 'success',
                                                    title: 'Persona switched',
                                                    description: `${formatPersonaTitle(persona)} activated.`,
                                                });
                                                handlePersonaLanding(persona.type);
                                            })
                                            .catch((error) => {
                                                const message =
                                                    error instanceof Error
                                                        ? error.message
                                                        : 'Unable to switch persona right now.';

                                                publishToast({
                                                    variant: 'destructive',
                                                    title: 'Switch failed',
                                                    description: message,
                                                });
                                            })
                                            .finally(() => setIsSwitchingPersona(false));
                                    }}
                                >
                                    <div className="flex w-full items-center justify-between gap-2">
                                        <div>
                                            <p className="text-sm font-medium leading-none">
                                                {formatPersonaTitle(persona)}
                                            </p>
                                            {formatPersonaMeta(persona) ? (
                                                <p className="text-xs text-muted-foreground">
                                                    {formatPersonaMeta(persona)}
                                                </p>
                                            ) : null}
                                        </div>
                                        {persona.key === activePersona?.key ? (
                                            <Check className="h-4 w-4 text-primary" />
                                        ) : null}
                                    </div>
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                ) : null}

                {supplierBadgeLabel ? (
                    <Badge
                        variant="secondary"
                        className="hidden max-w-xs truncate md:inline-flex"
                        title={supplierBadgeLabel}
                    >
                        {supplierBadgeLabel}
                    </Badge>
                ) : null}

                {canSwitchCompanies ? (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                className="hidden items-center gap-2 text-sm text-muted-foreground hover:text-foreground md:flex"
                            >
                                <span className="truncate max-w-[180px]">{companyName}</span>
                                {switchCompany.isPending ? (
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                ) : (
                                    <ChevronDown className="h-4 w-4" />
                                )}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-64">
                            <DropdownMenuLabel>Switch organization</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            {companies.map((company) => (
                                <DropdownMenuItem
                                    key={company.id}
                                    disabled={switchCompany.isPending || company.isActive}
                                    onSelect={(event) => {
                                        event.preventDefault();
                                        if (company.isActive || switchCompany.isPending) {
                                            return;
                                        }

                                        switchCompany
                                            .mutateAsync(company.id)
                                            .then(() => {
                                                publishToast({
                                                    variant: 'success',
                                                    title: 'Organization switched',
                                                    description: `Now working in ${company.name}.`,
                                                });
                                            })
                                            .catch((error) => {
                                                const message =
                                                    error instanceof ApiError
                                                        ? error.message
                                                        : 'Unable to switch organizations right now.';
                                                publishToast({
                                                    variant: 'destructive',
                                                    title: 'Switch failed',
                                                    description: message,
                                                });
                                            });
                                    }}
                                >
                                    <div className="flex w-full items-center justify-between gap-3">
                                        <div>
                                            <p className="text-sm font-medium leading-none">{company.name}</p>
                                            {company.role ? (
                                                <p className="text-xs capitalize text-muted-foreground">
                                                    {company.role.replace(/_/g, ' ')}
                                                </p>
                                            ) : null}
                                        </div>
                                        {company.isActive ? <Check className="h-4 w-4 text-primary" /> : null}
                                    </div>
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                ) : (
                    <Button
                        variant="ghost"
                        className="hidden items-center gap-2 text-sm text-muted-foreground hover:text-foreground md:flex"
                        disabled
                    >
                        <span className="truncate max-w-[180px]">{companyName}</span>
                    </Button>
                )}

                {isPlatformOperator || isSupplierPersona ? null : <NotificationBell />}

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" className="flex items-center gap-2">
                            <Avatar className="h-8 w-8">
                                {state.user?.avatar_url ? (
                                    <AvatarImage src={state.user.avatar_url} alt={state.user.name ?? state.user.email ?? 'User avatar'} />
                                ) : (
                                    <AvatarFallback>{initials}</AvatarFallback>
                                )}
                            </Avatar>
                            <span className="hidden text-sm font-medium md:inline">{state.user?.name ?? state.user?.email}</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-56">
                        <DropdownMenuLabel>
                            <div className="flex flex-col">
                                <span className="text-sm font-semibold">{state.user?.name ?? state.user?.email}</span>
                                <span className="text-xs text-muted-foreground">{companyName}</span>
                            </div>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onSelect={() => navigate('/app/settings')}>
                            Profile & Settings
                        </DropdownMenuItem>
                        <DropdownMenuItem onSelect={() => navigate('/app/settings/billing')}>
                            Billing
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onSelect={() => {
                                logout();
                                navigate('/login');
                            }}
                        >
                            Sign out
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>
    );
}
