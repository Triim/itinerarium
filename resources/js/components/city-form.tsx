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
        <form onSubmit={onSubmit} className="mt-4 max-w-xl space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="name">Name</Label>
                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                <InputError message={errors.name} />
            </div>

            {children}
        </form>
    );
}
