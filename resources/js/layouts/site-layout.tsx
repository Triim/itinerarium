import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import SiteFooter from '@/components/site-footer';
import SiteHeader from '@/components/site-header';

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
                <SiteHeader actions={actions} />

                <main className="mx-auto w-full max-w-4xl flex-1 px-6 py-8">{children}</main>

                <SiteFooter />
            </div>
        </>
    );
}
