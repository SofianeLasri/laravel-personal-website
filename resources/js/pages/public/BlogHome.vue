<script setup lang="ts">
import BlogArticleCard from '@/components/public/Blog/BlogArticleCard.vue';
import BlogHeroCard from '@/components/public/Blog/BlogHeroCard.vue';
import LightShape from '@/components/public/LightShape.vue';
import HeroSectionTitle from '@/components/public/Ui/HeroSectionTitle.vue';
import { useTranslation } from '@/composables/useTranslation';
import PublicAppLayout from '@/layouts/PublicAppLayout.vue';
import { SocialMediaLink, SSRBlogPost } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';

const page = usePage();
const { t } = useTranslation();

defineProps<{
    socialMediaLinks: SocialMediaLink[];
    heroPost: SSRBlogPost;
    recentPosts: SSRBlogPost[];
    hasMultiplePosts: boolean;
}>();

const pageTitle = 'Blog - Sofiane Lasri';
const pageDescription = 'Découvrez mes derniers articles sur le développement web, Laravel, Vue.js et mes critiques de jeux vidéo.';
const pageKeywords = 'Sofiane Lasri, blog, développeur web, Laravel, Vue.js, tutoriels, critiques jeux vidéo, articles tech';
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
        <meta property="og:video:width" content="1200" />
        <meta property="og:video:height" content="630" />

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
            <section class="mb-16 flex flex-col gap-6">
                <HeroSectionTitle>Blog.</HeroSectionTitle>
                <div class="flex flex-col gap-4">
                    <h3 class="text-design-system-title text-2xl font-bold">Dernier article</h3>
                    <BlogHeroCard :post="heroPost" />
                </div>
            </section>

            <!-- Recent Articles Section -->
            <section v-if="hasMultiplePosts" class="mb-16 flex flex-col gap-4">
                <h3 class="text-design-system-title text-2xl font-bold">Articles récents</h3>

                <!-- Articles Grid -->
                <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <BlogArticleCard v-for="post in recentPosts" :key="post.id" :post="post" />
                </div>
            </section>
        </div>
    </PublicAppLayout>
</template>
