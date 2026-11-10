import { Button } from '@/components/ui/button';
import { type City, type Selection, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

interface Props {
    cities: Pick<City, 'id' | 'name' | 'slug' | 'country'>[];
    selections: Pick<Selection, 'id' | 'title' | 'slug' | 'description'>[];
}

const LAYERS = ['Туристические места', 'Локации съёмок', 'Неформальные достопримечательности', 'Worth to try', 'Unique experience'];

export default function Welcome({ cities, selections }: Props) {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Карта для идеального маршрута" />
            <div className="bg-background text-foreground min-h-screen">
                <header className="mx-auto flex max-w-5xl items-center justify-between p-6">
                    <span className="text-lg font-semibold">Itinerarium</span>
                    <nav className="flex gap-2">
                        {auth.user ? (
                            <Button asChild variant="outline">
                                <Link href={route('dashboard')}>Мои маршруты</Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild variant="ghost">
                                    <Link href={route('login')}>Войти</Link>
                                </Button>
                                <Button asChild variant="outline">
                                    <Link href={route('register')}>Регистрация</Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <main className="mx-auto flex max-w-5xl flex-col gap-12 px-6 pb-16">
                    <section className="space-y-4 pt-10">
                        <h1 className="max-w-2xl text-4xl font-semibold tracking-tight">Карта города со слоями, которую вы настраиваете под себя</h1>
                        <p className="text-muted-foreground max-w-2xl">
                            Включайте нужные слои, собирайте точки в маршрут и делитесь им. Или возьмите готовый маршрут из авторских подборок.
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {LAYERS.map((l) => (
                                <span key={l} className="rounded-full border px-3 py-1 text-sm">
                                    {l}
                                </span>
                            ))}
                        </div>
                        <div className="flex gap-2 pt-2">
                            <Button asChild>
                                <Link href={route('cities.index')}>Открыть карту</Link>
                            </Button>
                            <Button asChild variant="outline">
                                <Link href={route('itineraries.index')}>Смотреть маршруты</Link>
                            </Button>
                        </div>
                    </section>

                    <section className="space-y-3">
                        <h2 className="text-xl font-semibold">Города</h2>
                        <div className="grid gap-3 sm:grid-cols-3">
                            {cities.map((c) => (
                                <Link
                                    key={c.id}
                                    href={route('cities.show', c.slug)}
                                    className="hover:bg-accent/50 rounded-xl border p-4 transition-colors"
                                >
                                    <div className="text-muted-foreground text-xs uppercase">{c.country}</div>
                                    <div className="font-semibold">{c.name}</div>
                                </Link>
                            ))}
                        </div>
                    </section>

                    {selections.length > 0 && (
                        <section className="space-y-3">
                            <h2 className="text-xl font-semibold">Подборки</h2>
                            <div className="grid gap-3 sm:grid-cols-3">
                                {selections.map((s) => (
                                    <Link
                                        key={s.id}
                                        href={route('selections.show', s.slug)}
                                        className="hover:bg-accent/50 rounded-xl border p-4 transition-colors"
                                    >
                                        <div className="font-semibold">{s.title}</div>
                                        {s.description && <p className="text-muted-foreground text-sm">{s.description}</p>}
                                    </Link>
                                ))}
                            </div>
                        </section>
                    )}
                </main>
            </div>
        </>
    );
}
