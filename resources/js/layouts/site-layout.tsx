import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

type Props = {
    children: ReactNode;
    title: string;
    actions?: ReactNode;
};

export default function SiteLayout({ children, title, actions }: Props) {
    return (
        <>
            <Head title={title} />
            <div className="flex min-h-screen flex-col bg-stone-50 text-stone-900">
                <header className="border-b border-stone-200 bg-white">
                    <div className="mx-auto flex max-w-4xl items-center justify-between px-6 py-5">
                        <Link href={route('home')} className="text-xl font-semibold">
                            Itinerarium
                        </Link>

                        <nav className="flex items-center gap-5 text-sm">
                            <Link href={route('itineraries.index')}>Itineraries</Link>
                            {actions}
                        </nav>
                    </div>
                </header>

                <main className="mx-auto w-full max-w-4xl flex-1 px-6 py-8">{children}</main>

                <footer className="border-t border-stone-200 bg-white">
                    <div className="mx-auto max-w-4xl px-6 py-5 text-sm text-stone-500">
                        Itinerarium
                    </div>
                </footer>
            </div>
        </>
    );
}
