import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FormEventHandler, ReactNode } from 'react';

type CityFormData = {
    name: string;
};

type Props = {
    data: CityFormData;
    setData: (field: keyof CityFormData, value: string) => void;
    errors: Partial<Record<keyof CityFormData, string>>;
    onSubmit: FormEventHandler;
    children: ReactNode;
};

export default function CityForm({ data, setData, errors, onSubmit, children }: Props) {
    return (
        <form onSubmit={onSubmit} className="mt-6 max-w-xl space-y-8">
            <div className="grid gap-2">
                <Label htmlFor="name">Name</Label>
                <Input
                    id="name"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                    aria-invalid={errors.name ? true : undefined}
                    aria-describedby={errors.name ? 'name-help name-error' : 'name-help'}
                />
                <p id="name-help" className="text-muted-foreground text-sm">
                    At least 3 characters.
                </p>
                <InputError id="name-error" message={errors.name} />
            </div>

            <div className="flex flex-wrap gap-4 border-t pt-6">{children}</div>
        </form>
    );
}
