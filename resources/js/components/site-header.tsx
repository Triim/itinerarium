import { siteMenu } from '@/config/site-menu';
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

type Props = {
    actions?: ReactNode;
};

export default function SiteHeader({ actions }: Props) {
    return (
        <header className="border-b border-stone-200 bg-white">
            <div className="mx-auto flex max-w-4xl items-center justify-between px-6 py-5">
                <Link href={route('home')} className="text-xl font-semibold">
                    Itinerarium
                </Link>

                <nav className="flex items-center gap-5 text-sm">
                    {siteMenu.map((item) => (
                        <Link key={item.routeName} href={route(item.routeName)}>
                            {item.label}
                        </Link>
                    ))}
                    {actions}
                </nav>
            </div>
        </header>
    );
}
