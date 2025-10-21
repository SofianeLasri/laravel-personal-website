<script setup lang="ts">
import BlogNotificationPopup from '@/components/public/BlogNotificationPopup.vue';
import BorderGlow from '@/components/public/BorderGlow.vue';
import DotMatrixMask from '@/components/public/DotMatrixMask.vue';
import LanguagePopup from '@/components/public/LanguagePopup.vue';
import Navbar from '@/components/public/Navbar/Navbar.vue';
import PopupCarousel from '@/components/public/PopupCarousel.vue';
import Footer from '@/components/public/Ui/Footer.vue';
import { SocialMediaLink } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    socialMediaLinks: SocialMediaLink[];
}

withDefaults(defineProps<Props>(), {
    socialMediaLinks: () => [],
});

const page = usePage();

const latestBlogPostData = computed(() => {
    const blogPost = page.props.latestBlogPost as { title: string; slug: string; published_at: string } | undefined;
    return blogPost;
});

const languagePopupShouldShow = () => {
    if (typeof window === 'undefined') return false;
    const dismissed = localStorage.getItem('language_popup_dismissed');
    const browserLanguage = page.props.browserLanguage as string | null;
    const locale = (page.props.locale as string) || 'fr';
    return dismissed !== 'true' && browserLanguage !== null && browserLanguage !== 'fr' && locale === 'fr';
};

const blogPopupShouldShow = () => {
    if (typeof window === 'undefined') return false;
    const latestBlogPost = page.props.latestBlogPost as { slug: string } | undefined;
    if (!latestBlogPost) return false;

    const lastSeenSlug = localStorage.getItem('blog_notification_last_seen');
    return lastSeenSlug !== latestBlogPost.slug;
};

// Configure popups in order
const popups = computed(() => [
    {
        id: 'language',
        component: LanguagePopup,
        shouldShow: languagePopupShouldShow,
    },
    {
        id: 'blog',
        component: BlogNotificationPopup,
        shouldShow: blogPopupShouldShow,
    },
]);
</script>

<template>
    <Head>
        <link rel="alternate" type="application/atom+xml" :href="route('feeds.blog')" />
    </Head>
    <div class="dots-background dark:bg-gray-990 relative flex min-h-screen flex-col items-center">
        <DotMatrixMask />
        <BorderGlow />
        <Navbar />
        <slot />
        <PopupCarousel :popups="popups" />
    </div>
    <Footer :social-media-links="socialMediaLinks" :latest-blog-post="latestBlogPostData" />
</template>

<style>
.dots-background {
    position: relative;
}
</style>
