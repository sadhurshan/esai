import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { resolveUrl } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const page = usePage();
    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Platform</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => {
                    const children = item.children ?? [];
                    const hasActiveChild = children.some((child) =>
                        page.url.startsWith(resolveUrl(child.href)),
                    );
                    const isActive =
                        (!item.disabled &&
                            page.url.startsWith(resolveUrl(item.href))) ||
                        hasActiveChild;

                    return (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild={!item.disabled}
                                disabled={item.disabled}
                                isActive={isActive}
                                tooltip={
                                    item.disabled
                                        ? undefined
                                        : { children: item.title }
                                }
                            >
                                {item.disabled ? (
                                    <span className="flex items-center gap-2 text-sm text-muted-foreground">
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                    </span>
                                ) : (
                                    <Link href={item.href} prefetch>
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                    </Link>
                                )}
                            </SidebarMenuButton>

                            {children.length > 0 && (
                                <div className="mt-1 pl-6">
                                    <SidebarMenu>
                                        {children.map((child) => {
                                            const childActive =
                                                !child.disabled &&
                                                page.url.startsWith(
                                                    resolveUrl(child.href),
                                                );

                                            return (
                                                <SidebarMenuItem
                                                    key={`${item.title}-${child.title}`}
                                                >
                                                    <SidebarMenuButton
                                                        asChild={!child.disabled}
                                                        disabled={child.disabled}
                                                        isActive={childActive}
                                                        tooltip={
                                                            child.disabled
                                                                ? undefined
                                                                : {
                                                                      children:
                                                                          child.title,
                                                                  }
                                                        }
                                                        size="sm"
                                                    >
                                                        {child.disabled ? (
                                                            <span className="flex items-center gap-2 text-xs text-muted-foreground">
                                                                {child.icon && (
                                                                    <child.icon className="h-3.5 w-3.5" />
                                                                )}
                                                                <span>{child.title}</span>
                                                            </span>
                                                        ) : (
                                                            <Link
                                                                href={child.href}
                                                                prefetch
                                                            >
                                                                {child.icon && (
                                                                    <child.icon className="h-3.5 w-3.5" />
                                                                )}
                                                                <span>{child.title}</span>
                                                            </Link>
                                                        )}
                                                    </SidebarMenuButton>
                                                </SidebarMenuItem>
                                            );
                                        })}
                                    </SidebarMenu>
                                </div>
                            )}
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
