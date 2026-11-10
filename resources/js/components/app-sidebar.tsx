import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Library, Map, PlusCircle, Route, User } from 'lucide-react';
import AppLogo from './app-logo';

const exploreNavItems: NavItem[] = [
    { title: 'Города', url: '/cities', icon: Map },
    { title: 'Маршруты', url: '/routes', icon: Route },
    { title: 'Подборки', url: '/collections', icon: Library },
];

const authorNavItems: NavItem[] = [
    { title: 'Мои маршруты', url: '/dashboard', icon: User },
    { title: 'Новый маршрут', url: '/routes/create', icon: PlusCircle },
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
                <NavMain label="Исследовать" items={exploreNavItems} />
                {auth.user && <NavMain label="Автор" items={authorNavItems} />}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
