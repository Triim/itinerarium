import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Building2, Home, LayoutGrid, MapPin, Route } from 'lucide-react';
import AppLogo from './app-logo';

const manageNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'My Itineraries',
        url: '/user/itineraries',
        icon: Route,
    },
    {
        title: 'Manage Places',
        url: '/user/places',
        icon: MapPin,
    },
    {
        title: 'Manage Cities',
        url: '/user/cities',
        icon: Building2,
    },
];

const adminOnlyUrls = ['/user/places', '/user/cities'];

const exploreNavItems: NavItem[] = [
    {
        title: 'Home',
        url: '/',
        icon: Home,
    },
    {
        title: 'Itineraries',
        url: '/itineraries',
        icon: Route,
    },
    {
        title: 'Cities',
        url: '/cities',
        icon: Building2,
    },
    {
        title: 'Places',
        url: '/places',
        icon: MapPin,
    },
];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const visibleManageNavItems = manageNavItems.filter((item) => auth.user.is_admin || !adminOnlyUrls.includes(item.url));

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
                <NavMain label="Manage" items={visibleManageNavItems} />
                <NavMain label="Explore" items={exploreNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
