import { Branding } from '@/config/branding';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Bell, ChevronDown } from 'lucide-react';
import { useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '@/contexts/auth-context';
import { WorkspaceBreadcrumbs } from './breadcrumbs';

export function TopBar() {
    const navigate = useNavigate();
    const { state, logout } = useAuth();
    const companyName = state.company?.name ?? 'Company';
    const initials = useMemo(() => {
        const source = state.user?.name ?? state.user?.email ?? 'User';
        return source
            .split(' ')
            .map((part) => part.charAt(0))
            .join('')
            .slice(0, 2)
            .toUpperCase();
    }, [state.user?.email, state.user?.name]);

    return (
        <header className="flex h-16 items-center gap-4 border-b bg-background px-4">
            <div className="flex flex-1 items-center gap-3">
                <SidebarTrigger className="md:hidden" />
                <img src={Branding.logo.default} alt={Branding.name} className="hidden h-7 md:block" />
                <WorkspaceBreadcrumbs />
            </div>

            <div className="flex items-center gap-3">
                <Button
                    variant="ghost"
                    className="hidden items-center gap-2 text-sm text-muted-foreground hover:text-foreground md:flex"
                >
                    <span className="truncate max-w-[160px]">{companyName}</span>
                    <ChevronDown className="h-4 w-4" />
                </Button>

                {/* TODO: wire unread count via Notifications API hook once available in the SDK. */}
                <Button variant="ghost" size="icon" className="relative">
                    <Bell className="h-5 w-5" />
                    <span className="absolute right-1 top-1 inline-flex h-2 w-2 rounded-full bg-brand-accent" />
                </Button>

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
                        <DropdownMenuItem onSelect={() => navigate('/app/settings?tab=billing')}>
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
