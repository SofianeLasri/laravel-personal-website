<script setup lang="ts">
import BlogCategoryBadge from '@/components/public/Blog/BlogCategoryBadge.vue';
import { useRoute } from '@/composables/useRoute';
import { useTranslation } from '@/composables/useTranslation';
import { SSRBlogPost } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Newspaper, X } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const page = usePage();
const route = useRoute();
const { t } = useTranslation();

const latestBlogPost = computed(() => (page.props.latestBlogPost as SSRBlogPost | null) || null);

const isVisible = ref(false);
const isDismissed = ref(false);

const shouldShowPopup = computed(() => {
    return !isDismissed.value && latestBlogPost.value !== null;
});

const dismissPopup = () => {
    isDismissed.value = true;
    isVisible.value = false;
    if (typeof window !== 'undefined') {
        localStorage.setItem('blog_notification_dismissed', 'true');
    }
};

onMounted(() => {
    if (typeof window !== 'undefined') {
        const dismissed = localStorage.getItem('blog_notification_dismissed');
        if (dismissed === 'true') {
            isDismissed.value = true;
            return;
        }

        if (shouldShowPopup.value) {
            // Show after 3.5 seconds (after LanguagePopup which appears at 2s)
            setTimeout(() => {
                isVisible.value = true;
            }, 3500);
        }
    }
});
</script>

<template>
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="transform translate-y-full opacity-0"
        enter-to-class="transform translate-y-0 opacity-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="transform translate-y-0 opacity-100"
        leave-to-class="transform translate-y-full opacity-0"
    >
        <div
            v-if="isVisible && shouldShowPopup && latestBlogPost"
            class="bg-action-container-outer-color action-container-outer-shadow action-container-outer-border action-container-background-blur fixed! left-4 bottom-4 z-50 w-80 rounded-3xl p-2"
        >
            <div class="action-container-inner-shadow flex flex-col gap-3 rounded-2xl border bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <!-- Header -->
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-3">
                        <div class="bg-atomic-tangerine-100 dark:bg-atomic-tangerine-900 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full">
                            <Newspaper class="text-atomic-tangerine-600 dark:text-atomic-tangerine-400 h-4 w-4" />
                        </div>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ t('home.blog_notification.title') }}</p>
                    </div>
                    <button
                        class="no-glow hover:text-atomic-tangerine-600 dark:hover:text-atomic-tangerine-400 h-6 w-6 flex-shrink-0 text-gray-400 transition-colors duration-200"
                        @click="dismissPopup"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Blog Post Card -->
                <Link
                    :href="route('public.blog.post', { slug: latestBlogPost.slug })"
                    class="group flex flex-col gap-2 rounded-lg transition-all hover:bg-gray-50 dark:hover:bg-gray-800"
                    @click="dismissPopup"
                >
                    <!-- Cover Image with Category Badge -->
                    <div class="relative aspect-video w-full overflow-hidden rounded-lg">
                        <div class="absolute z-10 flex h-full w-full p-2">
                            <div>
                                <BlogCategoryBadge :category="latestBlogPost.category" size="sm" />
                            </div>
                        </div>
                        <picture>
                            <source :srcset="latestBlogPost.coverImage.avif.small" type="image/avif" />
                            <source :srcset="latestBlogPost.coverImage.webp.small" type="image/webp" />
                            <img
                                :src="latestBlogPost.coverImage.webp.small"
                                :alt="`${latestBlogPost.title}`"
                                class="h-full w-full object-cover"
                                loading="lazy"
                            />
                        </picture>
                    </div>

                    <!-- Post Info -->
                    <div class="flex flex-col gap-1 px-1">
                        <h4 class="text-design-system-title group-hover:text-primary text-sm font-semibold leading-tight transition-colors duration-200">
                            {{ latestBlogPost.title }}
                        </h4>
                        <div class="text-design-system-paragraph text-xs">{{ latestBlogPost.publishedAtFormatted }}</div>
                    </div>
                </Link>

                <!-- Action Button -->
                <div class="flex gap-2">
                    <Link
                        :href="route('public.blog.home')"
                        class="no-glow bg-atomic-tangerine-400 hover:bg-atomic-tangerine-500 flex-1 rounded-md px-3 py-1.5 text-center text-xs font-medium text-white shadow-sm transition-colors duration-200"
                        @click="dismissPopup"
                    >
                        {{ t('home.blog_notification.discover_blog') }}
                    </Link>
                    <button
                        class="no-glow px-3 py-1.5 text-xs font-medium text-gray-600 transition-colors duration-200 hover:text-gray-800 dark:text-gray-300 dark:hover:text-gray-100"
                        @click="dismissPopup"
                    >
                        {{ t('home.blog_notification.dismiss') }}
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>
