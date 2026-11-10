import { FlashMessage } from '@/components/flash-message';
import { ItineraryCard } from '@/components/itinerary-card';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Itinerary } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My routes', href: '/dashboard' }];

export default function Dashboard({ itineraries }: { itineraries: Itinerary[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My routes" />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessage />
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-2xl font-semibold">My routes</h1>
                    <Button asChild>
                        <Link href={route('itineraries.create')}>
                            <Plus /> New route
                        </Link>
                    </Button>
                </div>
                {itineraries.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        You have no routes yet. Open a{' '}
                        <Link href={route('cities.index')} className="underline underline-offset-4">
                            city map
                        </Link>{' '}
                        and build your first one.
                    </p>
                ) : (
                    <div className="grid gap-4 md:grid-cols-3">
                        {itineraries.map((it) => (
                            <ItineraryCard key={it.id} itinerary={it} showStatus />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
