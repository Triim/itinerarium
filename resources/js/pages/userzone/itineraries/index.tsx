import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Itineraries',
        href: '/user/itineraries',
    },
];

type Status = 'draft' | 'pending' | 'published';

type Itinerary = {
    id: number;
    title: string;
    status: Status;
    user_id: number;
    city: { name: string };
    user: { name: string };
};

const statusLabels: Record<Status, string> = {
    draft: 'Draft',
    pending: 'Awaiting approval',
    published: 'Published',
};

type Props = {
    itineraries: Itinerary[];
};

export default function Index({ itineraries }: Props) {
    const { auth, errors } = usePage<SharedData & { errors: { status?: string } }>().props;
    // The server checks every action; this only decides which button to offer.
    const [processing, setProcessing] = useState<number | null>(null);

    const post = (name: 'userzone.itineraries.submit' | 'userzone.itineraries.approve', itinerary: Itinerary) => {
        router.post(
            route(name, itinerary.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(itinerary.id),
                onFinish: () => setProcessing(null),
            },
        );
    };

    const destroy = (itinerary: Itinerary) => {
        if (window.confirm(`Delete "${itinerary.title}"? This cannot be undone.`)) {
            router.delete(route('userzone.itineraries.destroy', itinerary.id), { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Manage itineraries" />
            <div className="p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold">Manage itineraries</h1>
                    <Button asChild>
                        <Link href={route('userzone.itineraries.create')}>Create Itinerary</Link>
                    </Button>
                </div>

                {errors.status && (
                    <p role="alert" className="text-destructive mt-4 text-sm">
                        {errors.status}
                    </p>
                )}

                {itineraries.length > 0 ? (
                    <ul className="mt-4 divide-y border-y">
                        {itineraries.map((itinerary) => (
                            <li key={itinerary.id} className="flex flex-wrap justify-between gap-x-4 gap-y-1 py-3">
                                <span className="min-w-0 break-words">{itinerary.title}</span>
                                <span className="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                                    <span className="text-muted-foreground">{itinerary.city.name}</span>
                                    <span className="text-muted-foreground">By {itinerary.user.name}</span>
                                    <Badge variant={itinerary.status === 'published' ? 'default' : 'outline'}>{statusLabels[itinerary.status]}</Badge>
                                    {itinerary.status === 'draft' && itinerary.user_id === auth.user.id && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            disabled={processing === itinerary.id}
                                            onClick={() => post('userzone.itineraries.submit', itinerary)}
                                            aria-label={`Submit ${itinerary.title} for review`}
                                        >
                                            Submit for review
                                        </Button>
                                    )}
                                    {itinerary.status === 'pending' && Boolean(auth.user.is_admin) && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            disabled={processing === itinerary.id}
                                            onClick={() => post('userzone.itineraries.approve', itinerary)}
                                            aria-label={`Approve ${itinerary.title}`}
                                        >
                                            Approve
                                        </Button>
                                    )}
                                    <Link
                                        href={route('userzone.itineraries.edit', itinerary.id)}
                                        className="underline underline-offset-4"
                                        aria-label={`Edit ${itinerary.title}`}
                                    >
                                        Edit
                                    </Link>
                                    <button
                                        type="button"
                                        onClick={() => destroy(itinerary)}
                                        className="text-destructive cursor-pointer underline underline-offset-4"
                                        aria-label={`Delete ${itinerary.title}`}
                                    >
                                        Delete
                                    </button>
                                </span>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-muted-foreground mt-4">No itineraries yet.</p>
                )}
            </div>
        </AppLayout>
    );
}
