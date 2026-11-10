import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';

export function FlashMessage() {
    const { flash } = usePage<SharedData>().props;
    if (!flash?.success) return null;

    return (
        <div className="flex items-center gap-2 rounded-lg border border-green-600/30 bg-green-600/10 px-4 py-2 text-sm text-green-800 dark:text-green-300">
            <CheckCircle2 className="size-4" />
            {flash.success}
        </div>
    );
}
