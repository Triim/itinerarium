import { siteMenu } from '@/config/site-menu';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

type Props = {
    actions?: ReactNode;
};

// Highlights "Itineraries" on both itineraries.index and itineraries.show, etc.
function isActiveRoute(routeName: string): boolean {
    return route().current(routeName.replace(/\.index$/, '.*'));
}

export default function SiteHeader({ actions }: Props) {
    const { auth, name } = usePage<SharedData>().props;

    return (
        <header className="border-b border-stone-200 bg-white">
            <div className="mx-auto flex max-w-4xl flex-wrap items-center justify-between gap-x-5 gap-y-3 px-6 py-5">
                <Link href={route('home')} className="text-xl font-semibold">
                    {name}
                </Link>

                <nav className="flex min-w-0 flex-wrap items-center gap-x-5 gap-y-2 text-sm">
                    {siteMenu.map((item) => {
                        const active = isActiveRoute(item.routeName);

                        return (
                            <Link
                                key={item.routeName}
                                href={route(item.routeName)}
                                aria-current={active ? 'page' : undefined}
                                className={active ? 'font-semibold underline underline-offset-4' : undefined}
                            >
                                {item.label}
                            </Link>
                        );
                    })}
                    {actions}
                    {auth.user ? (
                        <>
                            <span className="min-w-0 break-words text-stone-500">{auth.user.name}</span>
                            <Link href={route('userzone.itineraries.index')}>My Itineraries</Link>
                        </>
                    ) : (
                        <>
                            <Link href={route('login')}>Login</Link>
                            <Link href={route('register')}>Register</Link>
                        </>
                    )}
                </nav>
            </div>
        </header>
    );
}
