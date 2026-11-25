import { siteMenu } from '@/config/site-menu';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

type Props = {
    actions?: ReactNode;
};

export default function SiteHeader({ actions }: Props) {
    const { auth } = usePage<SharedData>().props;

    return (
        <header className="border-b border-stone-200 bg-white">
            <div className="mx-auto flex max-w-4xl flex-wrap items-center justify-between gap-x-5 gap-y-3 px-6 py-5">
                <Link href={route('home')} className="text-xl font-semibold">
                    Itinerarium
                </Link>

                <nav className="flex min-w-0 flex-wrap items-center gap-x-5 gap-y-2 text-sm">
                    {siteMenu.map((item) => (
                        <Link key={item.routeName} href={route(item.routeName)}>
                            {item.label}
                        </Link>
                    ))}
                    {actions}
                    {auth.user ? (
                        <>
                            <span className="min-w-0 break-words text-stone-500">{auth.user.name}</span>
                            <Link href={route('dashboard')}>Dashboard</Link>
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
