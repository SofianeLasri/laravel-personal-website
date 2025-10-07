<script setup lang="ts">
import BlogCategoryBadge from '@/components/public/Blog/BlogCategoryBadge.vue';
import PopupButton from '@/components/public/Ui/Button/PopupButton.vue';
import { useRoute } from '@/composables/useRoute';
import { useTranslation } from '@/composables/useTranslation';
import { SSRBlogPost } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Newspaper } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();
const route = useRoute();
const { t } = useTranslation();

const emit = defineEmits<{
    dismiss: [];
}>();

const latestBlogPost = computed(() => (page.props.latestBlogPost as SSRBlogPost | null) || null);

const shouldShow = () => {
    if (typeof window === 'undefined') return false;
    const dismissed = localStorage.getItem('blog_notification_dismissed');
    return dismissed !== 'true' && latestBlogPost.value !== null;
};

const dismissPopup = () => {
    if (typeof window !== 'undefined') {
        localStorage.setItem('blog_notification_dismissed', 'true');
    }
    emit('dismiss');
};

defineExpose({
    shouldShow,
});
</script>

<template>
    <div v-if="latestBlogPost" class="flex flex-col gap-3 p-4">
        <!-- Header -->
        <div class="flex items-center gap-3">
            <div class="bg-atomic-tangerine-100 dark:bg-atomic-tangerine-900 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full">
                <Newspaper class="text-atomic-tangerine-600 dark:text-atomic-tangerine-400 h-4 w-4" />
            </div>
            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ t('home.blog_notification.title') }}</p>
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
                <h4 class="text-design-system-title group-hover:text-primary text-sm leading-tight font-semibold transition-colors duration-200">
                    {{ latestBlogPost.title }}
                </h4>
                <div class="text-design-system-paragraph text-xs">{{ latestBlogPost.publishedAtFormatted }}</div>
            </div>
        </Link>

        <!-- Action Button -->
        <div class="flex gap-2">
            <PopupButton as="inertia-link" variant="primary" :href="route('public.blog.home')" class="flex-1 text-center" @click="dismissPopup">
                {{ t('home.blog_notification.discover_blog') }}
            </PopupButton>
            <PopupButton variant="ghost" @click="dismissPopup">
                {{ t('home.blog_notification.dismiss') }}
            </PopupButton>
        </div>
    </div>
</template>
