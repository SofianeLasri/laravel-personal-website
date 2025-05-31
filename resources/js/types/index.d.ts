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

interface Video {
    id: number;
    name: string;
    path: string;
    cover_picture_id: number;
    bunny_video_id: string;
    created_at: string;
    updated_at: string;
    cover_picture?: Picture;
}

// The following interfaces are used for the SSR (Server-Side Rendering) creation data
// that is sent to the frontend through Inertia.js.
interface SSRSimplifiedCreation {
    id: number;
    name: string;
    slug: string;
    logo: SSRPicture;
    coverImage: SSRPicture;
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
    screenshots: SSRScreenshot[];
    people: {
        id: number;
        name: string;
        url: string | null;
        picture: SSRPicture | null;
    }[];
    videos: SSRVideo[];
}

interface SSRTechnology {
    id: number;
    name: string;
    description: string;
    creationCount: number;
    type: TechnologyType;
    svgIcon: string;
}

interface SSRTechnologyExperience extends SSRTechnology {
    technologyId: number;
    description: string;
    typeLabel: string;
}

interface SSRExperience {
    id: number;
    title: string;
    organizationName: string;
    logo: SSRPicture | null;
    location: string;
    websiteUrl: string | null;
    shortDescription: string;
    fullDescription: string;
    technologies: SSRTechnology[];
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

interface SSRScreenshot {
    id: number;
    picture: SSRPicture;
    caption: string | null;
}

interface SSRVideo {
    id: number;
    bunnyVideoId: string;
    name: string;
    coverPicture: SSRPicture;
}

interface SSRPicture {
    filename: string;
    width: number | null;
    height: number | null;
    avif: {
        thumbnail: string;
        small: string;
        medium: string;
        large: string;
        full: string;
    };
    webp: {
        thumbnail: string;
        small: string;
        medium: string;
        large: string;
        full: string;
    };
}

interface SSRCertification {
    id: number;
    name: string;
    level: string | null;
    score: string | null;
    date: string;
    dateFormatted: string;
    link: string | null;
    picture: SSRPicture | null;
}

interface SSRCertificationsCareerData {
    certifications: SSRCertification[];
    educationExperiences: SSRExperience[];
    workExperiences: SSRExperience[];
}

export type BreadcrumbItemType = BreadcrumbItem;
