import { useMemo, useState } from 'react';
import { Bell } from 'lucide-react';
import { useNavigate } from 'react-router-dom';

import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetFooter, SheetHeader, SheetTitle, SheetDescription, SheetTrigger } from '@/components/ui/sheet';
import { NotificationList } from '@/components/notifications/notification-list';
import { useNotificationBadge } from '@/hooks/api/notifications/use-notification-badge';
import { cn } from '@/lib/utils';

export function NotificationBell() {
    const [open, setOpen] = useState(false);
    const navigate = useNavigate();
    const badgeQuery = useNotificationBadge();

    const unreadCount = badgeQuery.data?.unreadCount ?? 0;
    const hasUnread = unreadCount > 0;
    const badgeLabel = useMemo(() => {
        if (unreadCount > 99) {
            return '99+';
        }

        return String(unreadCount);
    }, [unreadCount]);

    const handleNavigate = (href: string) => {
        if (!href) {
            return;
        }

        if (/^https?:\/\//i.test(href)) {
            window.open(href, '_blank', 'noopener,noreferrer');
        } else {
            navigate(href);
        }
    };

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label={hasUnread ? `You have ${badgeLabel} unread notifications` : 'Open notifications'}
                    type="button"
                >
                    <Bell className="h-5 w-5" />
                    {hasUnread && (
                        <span
                            className={cn(
                                'absolute -right-0.5 -top-0.5 inline-flex min-h-[1.25rem] min-w-[1.25rem] items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-semibold text-destructive-foreground shadow-sm',
                            )}
                        >
                            {badgeLabel}
                        </span>
                    )}
                </Button>
            </SheetTrigger>
            <SheetContent className="sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>Notifications</SheetTitle>
                    <SheetDescription>Stay on top of RFQ, quote, PO, and invoice updates across your workspace.</SheetDescription>
                </SheetHeader>
                <NotificationList onNavigate={handleNavigate} onDismiss={() => setOpen(false)} />
                <SheetFooter>
                    <Button
                        variant="secondary"
                        className="w-full"
                        onClick={() => {
                            setOpen(false);
                            navigate('/app/notifications');
                        }}
                        type="button"
                    >
                        Open notification center
                    </Button>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}
