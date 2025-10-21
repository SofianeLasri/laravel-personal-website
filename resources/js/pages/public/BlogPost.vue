<script setup lang="ts">
import BlogCategoryBadge from '@/components/public/Blog/BlogCategoryBadge.vue';
import BlogImageGallery from '@/components/public/Blog/BlogImageGallery.vue';
import BlogVideoPlayer from '@/components/public/Blog/BlogVideoPlayer.vue';
import LightShape from '@/components/public/LightShape.vue';
import MarkdownViewer from '@/components/public/MarkdownViewer.vue';
import PublicAppLayout from '@/layouts/PublicAppLayout.vue';
import { SocialMediaLink, SSRBlogPostDetailed } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';

const page = usePage();

const props = defineProps<{
    socialMediaLinks: SocialMediaLink[];
    blogPost: SSRBlogPostDetailed;
}>();

const pageTitle = `${props.blogPost.title} - Sofiane Lasri`;
const pageDescription = props.blogPost.excerpt;
const pageKeywords = `Sofiane Lasri, blog, ${props.blogPost.category.name}, ${props.blogPost.type === 'game_review' ? 'critique jeu vidéo' : 'article'}, développement web`;
const pageUrl = page.props.ziggy.location;
const pageImage = props.blogPost.coverImage?.webp.large || '/opengraph-image-1200-630.jpg';
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

        <div class="relative z-10 container flex w-full min-w-0 flex-col items-center px-4 pt-16 pb-8">
            <div class="flex w-full max-w-5xl min-w-0 flex-col gap-8">
                <div class="flex flex-col gap-2">
                    <!-- Category and Date -->
                    <div class="flex gap-4">
                        <BlogCategoryBadge :category="blogPost.category" />
                        <div class="text-design-system-paragraph flex items-center">{{ blogPost.publishedAtFormatted }}</div>
                    </div>
                    <h1 class="text-design-system-title text-4xl font-bold">{{ blogPost.title }}</h1>
                </div>

                <!-- Hero Image -->
                <div
                    v-if="blogPost.coverImage"
                    class="flex aspect-32/15 shrink-0 overflow-hidden rounded-2xl shadow-[0px_0.25rem_0.5rem_0px_rgba(0,0,0,0.25)]"
                >
                    <picture class="h-full w-full">
                        <source :srcset="blogPost.coverImage.avif.large" type="image/avif" />
                        <source :srcset="blogPost.coverImage.webp.large" type="image/webp" />
                        <img
                            :src="blogPost.coverImage.webp.large"
                            :alt="`Image de couverture - ${blogPost.title}`"
                            class="h-full w-full object-cover"
                            loading="eager"
                        />
                    </picture>
                </div>

                <!-- Article Content -->
                <div class="flex min-w-0 flex-col gap-8">
                    <template v-for="(content, index) in blogPost.contents" :key="content.id">
                        <!-- Markdown Content -->
                        <div
                            v-if="content.content_type === 'App\\Models\\BlogContentMarkdown' && content.markdown"
                            class="min-w-0" :class="[index === 0 ? 'first-paragraph-large' : '']"
                        >
                            <MarkdownViewer :source="content.markdown" />
                        </div>

                        <!-- Gallery Content -->
                        <BlogImageGallery
                            v-else-if="content.content_type === 'App\\Models\\BlogContentGallery' && content.gallery"
                            :pictures="content.gallery.pictures"
                        />

                        <!-- Video Content -->
                        <BlogVideoPlayer
                            v-else-if="content.content_type === 'App\\Models\\BlogContentVideo' && content.video"
                            :video="content.video"
                        />
                    </template>
                </div>

                <!-- Game Review Section (if applicable) -->
                <div v-if="blogPost.gameReview" class="mt-8 rounded-2xl bg-gray-50 p-6 dark:bg-gray-900">
                    <h2 class="text-design-system-title mb-4 text-2xl font-bold">Critique du jeu</h2>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Game Info -->
                        <div class="space-y-3">
                            <h3 class="text-design-system-title text-xl font-semibold">{{ blogPost.gameReview.gameTitle }}</h3>

                            <div class="text-design-system-paragraph space-y-2">
                                <p v-if="blogPost.gameReview.developer"><strong>Développeur :</strong> {{ blogPost.gameReview.developer }}</p>
                                <p v-if="blogPost.gameReview.publisher"><strong>Éditeur :</strong> {{ blogPost.gameReview.publisher }}</p>
                                <p v-if="blogPost.gameReview.genre"><strong>Genre :</strong> {{ blogPost.gameReview.genre }}</p>
                                <p v-if="blogPost.gameReview.releaseDate"><strong>Date de sortie :</strong> {{ blogPost.gameReview.releaseDate }}</p>
                                <p v-if="blogPost.gameReview.platforms">
                                    <strong>Plateformes :</strong> {{ blogPost.gameReview.platforms.join(', ') }}
                                </p>
                            </div>

                            <!-- Rating -->
                            <div v-if="blogPost.gameReview.rating" class="mt-4">
                                <span
                                    class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium"
                                    :class="
                                        blogPost.gameReview.rating === 'positive'
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                            : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                    "
                                >
                                    {{ blogPost.gameReview.rating === 'positive' ? '👍 Recommandé' : '👎 Non recommandé' }}
                                </span>
                            </div>
                        </div>

                        <!-- Game Cover -->
                        <div v-if="blogPost.gameReview.coverPicture" class="flex justify-center">
                            <div class="aspect-[3/4] w-48 overflow-hidden rounded-xl shadow-lg">
                                <picture class="h-full w-full">
                                    <source :srcset="blogPost.gameReview.coverPicture.avif.medium" type="image/avif" />
                                    <source :srcset="blogPost.gameReview.coverPicture.webp.medium" type="image/webp" />
                                    <img
                                        :src="blogPost.gameReview.coverPicture.webp.medium"
                                        :alt="`Couverture - ${blogPost.gameReview.gameTitle}`"
                                        class="h-full w-full object-cover"
                                        loading="lazy"
                                    />
                                </picture>
                            </div>
                        </div>
                    </div>

                    <!-- Pros and Cons -->
                    <div v-if="blogPost.gameReview.pros || blogPost.gameReview.cons" class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div v-if="blogPost.gameReview.pros" class="space-y-2">
                            <h4 class="text-lg font-semibold text-green-600 dark:text-green-400">👍 Points positifs</h4>
                            <MarkdownViewer :source="blogPost.gameReview.pros" />
                        </div>

                        <div v-if="blogPost.gameReview.cons" class="space-y-2">
                            <h4 class="text-lg font-semibold text-red-600 dark:text-red-400">👎 Points négatifs</h4>
                            <MarkdownViewer :source="blogPost.gameReview.cons" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </PublicAppLayout>
</template>

<style scoped>
/* Style for the first paragraph in markdown content */
.first-paragraph-large :deep(.markdown-view p:first-child) {
    font-size: 1.125rem; /* text-lg */
    line-height: 1.75rem; /* text-lg line-height */
    color: rgb(49, 51, 54); /* text-gray-950 */
}

.dark .first-paragraph-large :deep(.markdown-view p:first-child) {
    color: rgb(244, 245, 245); /* text-gray-50 in dark mode */
}
</style>
