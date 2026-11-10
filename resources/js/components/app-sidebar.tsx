import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Library, Map, PlusCircle, Route, User } from 'lucide-react';
import AppLogo from './app-logo';

const exploreNavItems: NavItem[] = [
    { title: 'Cities', url: '/cities', icon: Map },
    { title: 'Routes', url: '/routes', icon: Route },
    { title: 'Collections', url: '/collections', icon: Library },
];

const authorNavItems: NavItem[] = [
    { title: 'My routes', url: '/dashboard', icon: User },
    { title: 'New route', url: '/routes/create', icon: PlusCircle },
];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain label="Explore" items={exploreNavItems} />
                {auth.user && <NavMain label="Author" items={authorNavItems} />}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
