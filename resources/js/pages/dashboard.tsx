import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Building2, MapPin, Route, type LucideIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

type Shortcut = {
    title: string;
    description: string;
    routeName: string;
    icon: LucideIcon;
};

const shortcuts: Shortcut[] = [
    {
        title: 'My Itineraries',
        description: 'Plan, edit and submit your own itineraries.',
        routeName: 'userzone.itineraries.index',
        icon: Route,
    },
    {
        title: 'Public Itineraries',
        description: 'Browse itineraries shared by other travellers.',
        routeName: 'itineraries.index',
        icon: Route,
    },
    {
        title: 'Cities',
        description: 'Explore the cities you can plan a trip around.',
        routeName: 'cities.index',
        icon: Building2,
    },
    {
        title: 'Places',
        description: 'Find places to add to an itinerary.',
        routeName: 'places.index',
        icon: MapPin,
    },
];

// The server enforces admin access; this only decides which links to offer.
const adminShortcuts: Shortcut[] = [
    {
        title: 'Manage Cities',
        description: 'Add and edit the cities available to everyone.',
        routeName: 'userzone.cities.index',
        icon: Building2,
    },
    {
        title: 'Manage Places',
        description: 'Add and edit places and their photographs.',
        routeName: 'userzone.places.index',
        icon: MapPin,
    },
];

function ShortcutList({ items }: { items: Shortcut[] }) {
    return (
        <ul className="grid gap-4 sm:grid-cols-2">
            {items.map((item) => (
                <li key={item.routeName}>
                    <Link
                        href={route(item.routeName)}
                        className="hover:bg-accent focus-visible:ring-ring flex h-full gap-3 rounded-xl border p-4 transition-colors focus-visible:ring-2 focus-visible:outline-none"
                    >
                        <item.icon aria-hidden="true" className="text-muted-foreground mt-0.5 size-5 shrink-0" />
                        <span className="min-w-0">
                            <span className="block font-medium">{item.title}</span>
                            <span className="text-muted-foreground block text-sm">{item.description}</span>
                        </span>
                    </Link>
                </li>
            ))}
        </ul>
    );
}

export default function Dashboard() {
    const { auth, name } = usePage<SharedData>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex flex-col gap-8 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="min-w-0">
                        <h1 className="text-xl font-semibold break-words">
                            Welcome to {name}, {auth.user.name}
                        </h1>
                        <p className="text-muted-foreground text-sm">Where would you like to go next?</p>
                    </div>
                    <Button asChild>
                        <Link href={route('userzone.itineraries.create')}>Create Itinerary</Link>
                    </Button>
                </div>

                <section aria-labelledby="dashboard-explore" className="flex flex-col gap-3">
                    <h2 id="dashboard-explore" className="font-semibold">
                        Explore and plan
                    </h2>
                    <ShortcutList items={shortcuts} />
                </section>

                {auth.user.is_admin && (
                    <section aria-labelledby="dashboard-admin" className="flex flex-col gap-3">
                        <h2 id="dashboard-admin" className="font-semibold">
                            Administration
                        </h2>
                        <ShortcutList items={adminShortcuts} />
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
