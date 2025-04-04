import type { PageProps } from '@inertiajs/core';
import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
    items?: {
        title: string;
        href: string;
    }[];
}

export interface SharedData extends PageProps {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

interface Translation {
    id: number;
    translation_key_id: number;
    locale: string;
    text: string;
}

interface TranslationKey {
    id: number;
    key: string;
    translations: Translation[];
}

type CreationType = 'portfolio' | 'game' | 'library' | 'website' | 'tool' | 'map' | 'other';

interface CreationWithTranslations {
    id: number;
    name: string;
    slug: string;
    logo_id: number;
    cover_image_id: number;
    type: CreationType;
    started_at: string;
    ended_at: string | null;
    short_description_translation_key_id: number;
    full_description_translation_key_id: number;
    external_url: string | null;
    source_code_url: string | null;
    featured: boolean;
    created_at: string;
    updated_at: string;
    short_description_translation_key: TranslationKey;
    full_description_translation_key: TranslationKey;
}

export type BreadcrumbItemType = BreadcrumbItem;
