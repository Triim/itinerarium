import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User | null;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    flash: { success: string | null };
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

// ---- Itinerarium domain ----

export interface Layer {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    color: string;
}

export interface City {
    id: number;
    name: string;
    slug: string;
    country: string;
    description: string | null;
    lat: number;
    lng: number;
    zoom: number;
    places?: Place[];
    places_count?: number;
    itineraries_count?: number;
}

export interface Place {
    id: number;
    city_id: number;
    layer_id: number;
    name: string;
    description: string | null;
    address: string | null;
    lat: number;
    lng: number;
    layer?: Layer;
    pivot?: { position: number; note: string | null };
}

export interface Author {
    id: number;
    name: string;
}

export interface Itinerary {
    id: number;
    user_id: number;
    city_id: number;
    title: string;
    description: string | null;
    is_published: boolean;
    created_at: string;
    updated_at: string;
    author?: Author;
    city?: City;
    places?: Place[];
    places_count?: number;
    selections?: Pick<Selection, 'id' | 'title' | 'slug'>[];
}

export interface Selection {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    author?: Author;
    city?: Pick<City, 'id' | 'name' | 'slug'> | null;
    itineraries?: Itinerary[];
    itineraries_count?: number;
}

export interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}
