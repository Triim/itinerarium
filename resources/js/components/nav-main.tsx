import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';

// A link is active on its own page and on its sub-pages (e.g. /user/itineraries/5/edit).
function isActiveUrl(itemUrl: string, pageUrl: string): boolean {
    const path = pageUrl.split('?')[0];

    if (itemUrl === '/') {
        return path === '/';
    }

    return path === itemUrl || path.startsWith(`${itemUrl}/`);
}

export function NavMain({ items = [], label }: { items: NavItem[]; label: string }) {
    const page = usePage();
    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{label}</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton asChild isActive={isActiveUrl(item.url, page.url)} tooltip={{ children: item.title }}>
                            <Link href={item.url} prefetch>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
