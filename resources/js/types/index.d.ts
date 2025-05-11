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

interface CreationWithTranslationsAndDrafts {
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
    drafts: CreationDraft[];
}

interface CreationDraft {
    id: number;
    name: string;
    slug: string;
    logo_id: number | null;
    cover_image_id: number | null;
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
    original_creation_id: number | null;
}

interface CreationDraftWithTranslations extends CreationDraft {
    short_description_translation_key: TranslationKey;
    full_description_translation_key: TranslationKey;
}

interface Picture {
    id: number;
    filename: string;
    width: number | null;
    height: number | null;
    size: number;
    path_original: string;
    created_at: string;
    updated_at: string;
}

interface Tag {
    id: number;
    name: string;
    slug: string;
    created_at: string;
    updated_at: string;
}

type TechnologyType = 'framework' | 'library' | 'language' | 'game_engine' | 'other';

interface Technology {
    id: number;
    name: string;
    type: TechnologyType;
    svg_icon: string;
    description_translation_key_id: number;
    created_at: string;
    updated_at: string;
    description_translation_key?: TranslationKey;
}

interface TechnologyWithCreationsCount extends Technology {
    creations_count: number;
}

interface TechnologyExperience {
    id: number;
    technology_id: number;
    technology?: TechnologyWithCreationsCount;
    description_translation_key_id: number;
    description_translation_key: TranslationKey;
}

interface Screenshot {
    id: number;
    creation_draft_id: number;
    picture_id: number;
    caption_translation_key_id: number | null;
    created_at: string;
    updated_at: string;
    picture: Picture;
    caption_translation_key?: TranslationKey;
}

interface Person {
    id: number;
    name: string;
    picture_id: number | null;
    picture?: Picture;
    created_at: string;
    updated_at: string;
}

interface Feature {
    id: number;
    creation_draft_id: number;
    title_translation_key_id: number;
    description_translation_key_id: number;
    picture_id: number | null;
    created_at: string;
    updated_at: string;
    picture?: Picture;
    title_translation_key?: TranslationKey;
    description_translation_key?: TranslationKey;
}

interface SocialMediaLink {
    id: number;
    icon_svg: string;
    name: string;
    url: string;
}

// The following interfaces are used for the SSR (Server-Side Rendering) creation data
// that is sent to the frontend through Inertia.js.
interface SSRSimplifiedCreation {
    id: number;
    name: string;
    slug: string;
    logo: string;
    coverImage: string;
    startedAt: string;
    endedAt: string | null;
    startedAtFormatted: string;
    endedAtFormatted: string | null;
    type: string;
    shortDescription: string;
    technologies: SSRTechnology[];
}

interface SSRFullCreation extends SSRSimplifiedCreation {
    fullDescription: string;
    externalUrl: string | null;
    sourceCodeUrl: string | null;
    features: SSRFeature[];
}

interface SSRTechnology {
    id: number;
    name: string;
    creationCount: number;
    type: TechnologyType;
    svgIcon: string;
}

interface SSRTechnologyExperience extends SSRTechnology {
    description: string;
    typeLabel: string;
}

interface SSRExperience {
    id: number;
    title: string;
    organizationName: string;
    logo: string;
    location: string;
    websiteUrl: string;
    shortDescription: string;
    fullDescription: string;
    technologies: {
        name: string;
        svgIcon: string;
        description: string;
    };
    type: 'emploi' | 'formation';
    startedAt: string;
    endedAt: string | null;
    startedAtFormatted: string;
    endedAtFormatted: string | null;
}

interface SSRFeature {
    id: number;
    title: string;
    description: string;
    picture: string | null;
}

export type BreadcrumbItemType = BreadcrumbItem;
