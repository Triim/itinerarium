import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

export default function SiteFooter() {
    const { name } = usePage<SharedData>().props;

    return (
        <footer className="border-t border-stone-200 bg-white">
            <div className="mx-auto max-w-4xl px-6 py-5 text-sm text-stone-500">{name}</div>
        </footer>
    );
}
