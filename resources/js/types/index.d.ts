import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

export interface GameReview {
    id: number;
    blog_post_id: number;
    game_title: string;
    release_date: string | null;
    genre: string | null;
    developer: string | null;
    publisher: string | null;
    platforms: string[] | null;
    cover_picture_id: number | null;
    pros_translation_key_id: number | null;
    cons_translation_key_id: number | null;
    rating: 'positive' | 'negative' | null;
    created_at: string;
    updated_at: string;
}

interface GameReviewDraft {
    id: number;
    game_title: string;
    release_date: string | null;
    genre: string;
    developer: string;
    publisher: string;
    platforms: string[];
    cover_picture_id: number | null;
    pros_translation_key_id: number | null;
    cons_translation_key_id: number | null;
    rating: 'positive' | 'negative' | null;
    pros_translation_key?: {
        translations: Array<{
            locale: string;
            text: string;
        }>;
    };
    cons_translation_key?: {
        translations: Array<{
            locale: string;
            text: string;
        }>;
    };
    links?: GameReviewLink[];
}

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

export type AppPageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
};

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

type BlogPostType = 'article' | 'game_review';

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

interface BlogPost {
    id: number;
    slug: string;
    title_translation_key_id: number;
    type: BlogPostType;
    category_id: number;
    cover_picture_id: number | null;
    published_at: string;
    created_at: string;
    updated_at: string;
}

interface BlogCategory {
    id: number;
    slug: string;
    name_translation_key_id: number;
    color: string; // TODO: Use predefined set of colors
    order: number;
    created_at: string;
    updated_at: string;
    name_translation_key: TranslationKey;
}

interface BlogContentMarkdown {
    id: number;
    translation_key_id: number;
    created_at: string;
    updated_at: string;
    translation_key?: TranslationKey;
}

interface BlogContentGallery {
    id: number;
    layout?: string;
    columns?: number;
    created_at: string;
    updated_at: string;
    pictures?: Picture[];
}

interface BlogContentVideo {
    id: number;
    video_id: number | null;
    caption_translation_key_id: number | null;
    created_at: string;
    updated_at: string;
    video?: Video;
    caption_translation_key?: TranslationKey;
}

type BlogContent = BlogContentMarkdown | BlogContentGallery | BlogContentVideo;

interface BlogPostDraftWithAllRelations {
    id: number;
    original_blog_post_id: number | null;
    slug: string;
    title_translation_key_id: number;
    type: BlogPostType;
    category_id: number;
    cover_picture_id: number | null;
    published_at: string | null;
    created_at: string;
    updated_at: string;
    title_translation_key: TranslationKey;
    category: BlogCategory;
    cover_picture: Picture | null;
    original_blog_post: BlogPost | null;
    contents: BlogPostDraftContent[];
    game_review_draft: GameReviewDraft | null;
}

interface BlogPostWithAllRelations {
    id: number;
    slug: string;
    title_translation_key_id: number;
    type: BlogPostType;
    category_id: number;
    cover_picture_id: number | null;
    published_at: string;
    created_at: string;
    updated_at: string;
    title_translation_key: TranslationKey;
    category: BlogCategory;
    cover_picture: Picture | null;
    drafts: BlogPostDraftWithAllRelations[];
}

interface BlogPostDraftContent {
    id: number;
    blog_post_draft_id: number;
    content_type: 'App\\Models\\BlogContentGallery' | 'App\\Models\\BlogContentMarkdown' | 'App\\Models\\BlogContentVideo';
    content_id: number;
    order: number;
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
    icon_picture_id: number;
    icon_picture?: Picture;
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
    status: 'pending' | 'transcoding' | 'ready' | 'error';
    visibility: 'private' | 'public';
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
    githubData: {
        name: string;
        description: string | null;
        stars: number;
        forks: number;
        watchers: number;
        language: string | null;
        topics: string[];
        license: string | null;
        updated_at: string;
        created_at: string;
        open_issues: number;
        default_branch: string;
        size: number;
        url: string;
        homepage: string | null;
    } | null;
    githubLanguages: Record<string, number> | null;
    packagistData: {
        name: string;
        description: string | null;
        downloads: number;
        daily_downloads: number;
        monthly_downloads: number;
        stars: number;
        dependents: number;
        suggesters: number;
        type: string | null;
        repository: string | null;
        github_stars: number | null;
        github_watchers: number | null;
        github_forks: number | null;
        github_open_issues: number | null;
        language: string | null;
        license: string[] | null;
        latest_version: string | null;
        latest_stable_version: string | null;
        created_at: string | null;
        updated_at: string | null;
        url: string;
        maintainers: Array<{
            name: string;
            avatar_url: string | null;
        }>;
        php_version: string | null;
        laravel_version: string | null;
    } | null;
}

interface SSRTechnology {
    id: number;
    name: string;
    description: string;
    creationCount: number;
    type: TechnologyType;
    iconPicture: SSRPicture;
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
    slug: string;
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
    libraryId: string;
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
    jpg: {
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

// Blog SSR types for public pages
interface SSRBlogPost {
    id: number;
    title: string;
    slug: string;
    type: BlogPostType;
    category: {
        name: string;
        color: string;
    };
    coverImage: SSRPicture;
    publishedAt: string;
    publishedAtFormatted: string;
    excerpt: string;
}

interface SSRBlogPostDetailed extends SSRBlogPost {
    contents: Array<{
        id: number;
        order: number;
        content_type: string;
        markdown?: string;
        gallery?: {
            id: number;
            pictures: SSRPicture[];
        };
    }>;
    gameReview?: {
        gameTitle: string;
        releaseDate: string | null;
        genre: string | null;
        developer: string | null;
        publisher: string | null;
        platforms: string[] | null;
        rating: 'positive' | 'negative' | null;
        pros: string | null;
        cons: string | null;
        coverPicture: SSRPicture | null;
    };
}

interface SSRBlogCategory {
    name: string;
    color: string;
}

// Search-related types
interface BlogCategoryFilter {
    id: number;
    name: string;
    slug: string;
    color: string;
}

interface BlogTypeFilter {
    value: string;
    label: string;
    icon: string;
}

type SSRSearchResultCreation = SSRSimplifiedCreation & {
    resultType: 'creation';
};

type SSRSearchResultBlogPost = SSRBlogPost & {
    resultType: 'blogPost';
};

type SSRSearchResult = SSRSearchResultCreation | SSRSearchResultBlogPost;

export type BreadcrumbItemType = BreadcrumbItem;
