import SiteFooter from '@/components/site-footer';
import SiteHeader from '@/components/site-header';
import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

type Props = {
    children: ReactNode;
    title: string;
};

export default function SiteLayout({ children, title }: Props) {
    return (
        <>
            <Head title={title} />
            <div className="flex min-h-screen flex-col bg-stone-50 text-stone-900">
                <SiteHeader />

                <main className="mx-auto w-full max-w-4xl flex-1 px-4 py-8 sm:px-6">{children}</main>

                <SiteFooter />
            </div>
        </>
    );
}
