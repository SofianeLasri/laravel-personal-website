<script setup lang="ts">
import LightShape from '@/components/public/LightShape.vue';
import BlogFilters from '@/components/public/Blog/BlogFilters.vue';
import BlogGrid from '@/components/public/Blog/BlogGrid.vue';
import BlogPagination from '@/components/public/Blog/BlogPagination.vue';
import HeroSectionTitle from '@/components/public/Ui/HeroSectionTitle.vue';
import PublicAppLayout from '@/layouts/PublicAppLayout.vue';
import { SocialMediaLink, SSRBlogPost } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';

const page = usePage();

interface BlogIndexProps {
    socialMediaLinks: SocialMediaLink[];
    posts: {
        data: SSRBlogPost[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    categories: Array<{
        id: number;
        name: string;
        slug: string;
        color: string;
    }>;
    currentFilters: {
        category?: string;
        sort?: string;
    };
}

const props = defineProps<BlogIndexProps>();

const pageTitle = 'Tous les articles - Blog - Sofiane Lasri';
const pageDescription = 'Découvrez tous mes articles sur le développement web, Laravel, Vue.js, mes tutoriels et critiques de jeux vidéo.';
const pageKeywords = 'Sofiane Lasri, blog, articles, développeur web, Laravel, Vue.js, tutoriels, critiques jeux vidéo';
const pageUrl = page.props.ziggy.location;
const pageImage = '/opengraph-image-1200-630.jpg';
</script>

<template>
    <Head :title="pageTitle">
        <!-- SEO Meta Tags -->
        <meta name="description" :content="pageDescription" />
        <meta name="keywords" :content="pageKeywords" />
        <meta name="robots" content="index, follow" />

        <!-- Open Graph -->
        <meta property="og:type" content="website" />
        <meta property="og:title" :content="pageTitle" />
        <meta property="og:description" :content="pageDescription" />
        <meta property="og:url" :content="pageUrl" />
        <meta property="og:image" :content="pageImage" />
        <meta property="og:image:width" content="1200" />
        <meta property="og:image:height" content="630" />

        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" :content="pageTitle" />
        <meta name="twitter:description" :content="pageDescription" />
        <meta name="twitter:image" :content="pageImage" />
    </Head>

    <PublicAppLayout :social-media-links="socialMediaLinks">
        <div class="absolute top-0 left-0 z-0 h-full w-full overflow-hidden">
            <LightShape class="absolute top-0 left-[-27rem] xl:left-[-15rem]" />
            <LightShape class="absolute top-[40rem] right-[-27rem] xl:right-[-15rem]" />
            <LightShape class="absolute top-[80rem] left-[-27rem] xl:left-[-15rem]" />
        </div>

        <div class="relative z-10 container px-4 pt-16 pb-8">
            <!-- Hero Section -->
            <section class="mb-12 flex flex-col gap-6">
                <HeroSectionTitle>Tous les articles.</HeroSectionTitle>

                <!-- Stats -->
                <div class="text-design-system-paragraph flex gap-4">
                    <span>{{ posts.total }} articles publiés</span>
                    <span>•</span>
                    <span>{{ categories.length }} catégories</span>
                </div>
            </section>

            <!-- Main Content with Sidebar Layout -->
            <div class="flex flex-col gap-8 lg:flex-row">
                <!-- Filters Sidebar -->
                <div class="w-full lg:w-72">
                    <BlogFilters :categories="categories" :current-filters="currentFilters" />
                </div>

                <!-- Articles Grid Section -->
                <div class="flex-1">
                    <section class="mb-12">
                        <BlogGrid :posts="posts.data" />
                    </section>

                    <!-- Pagination -->
                    <section class="flex justify-center">
                        <BlogPagination
                            :current-page="posts.current_page"
                            :last-page="posts.last_page"
                            :total="posts.total"
                            :from="posts.from"
                            :to="posts.to"
                        />
                    </section>
                </div>
            </div>
        </div>
    </PublicAppLayout>
</template>