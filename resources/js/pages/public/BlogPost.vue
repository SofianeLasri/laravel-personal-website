<script setup lang="ts">
import BlogCategoryBadge from '@/components/public/Blog/BlogCategoryBadge.vue';
import LightShape from '@/components/public/LightShape.vue';
import PublicAppLayout from '@/layouts/PublicAppLayout.vue';
import { SocialMediaLink } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';

const page = usePage();

defineProps<{
    socialMediaLinks: SocialMediaLink[];
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

        <div class="relative z-10 container flex flex-col items-center px-4 pt-16 pb-8">
            <div class="flex max-w-5xl flex-col gap-8">
                <div class="flex flex-col gap-2">
                    <!-- Category and Date -->
                    <div class="flex gap-4">
                        <BlogCategoryBadge :category="{ name: 'Placeholder', color: 'orange' }" />
                        <div class="text-design-system-paragraph flex items-center">1er septembre 2025</div>
                    </div>
                    <h1 class="text-design-system-title text-4xl font-bold">Placeholder article title</h1>
                </div>

                <!-- Hero Image -->
                <div class="flex aspect-32/15 shrink-0 overflow-hidden rounded-2xl shadow-[0px_0.25rem_0.5rem_0px_rgba(0,0,0,0.25)]">
                    <img alt="Image de couverture" class="h-full w-full object-cover" loading="eager" src="https://placehold.co/1024x462" />
                </div>

                <div class="flex flex-col gap-8">
                    <p class="text-lg text-gray-950">
                        The first paragraph must have the font height set to 18px (or text-lg), and have text-gray 950 color.
                    </p>
                    <p class="text-design-system-paragraph">Others are normal ones.</p>
                </div>
            </div>
        </div>
    </PublicAppLayout>
</template>
