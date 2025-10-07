<script setup lang="ts">
import Logo from '@/components/public/Logo.vue';
import SvgIcon from '@/components/public/SvgIcon.vue';
import { useTranslation } from '@/composables/useTranslation';
import { SocialMediaLink, SSRBlogPost } from '@/types';
import { Link } from '@inertiajs/vue3';

defineProps<{
    socialMediaLinks: SocialMediaLink[];
    latestBlogPost?: SSRBlogPost | null;
}>();

const { t } = useTranslation();
</script>

<template>
    <div class="relative flex flex-col overflow-hidden bg-black dark:bg-gray-900">
        <div
            class="flex w-full flex-col items-center justify-start gap-2.5 rounded-br-2xl rounded-bl-2xl border-t bg-gray-100 py-8 md:py-16 dark:border-gray-800 dark:bg-gray-950"
        >
            <div class="container space-y-8 px-4 md:space-y-0">
                <!-- Section principale du footer -->
                <div class="flex flex-col gap-8 md:flex-row md:items-center md:justify-between md:gap-4">
                    <!-- Logo et informations -->
                    <div class="flex flex-col gap-4">
                        <Logo class="h-8" />
                        <div class="flex flex-col justify-start">
                            <div class="text-xl font-bold text-black dark:text-gray-100">{{ t('footer.name') }}</div>
                            <div class="text-design-system-paragraph text-base font-normal dark:text-gray-400">{{ t('footer.title') }}</div>
                        </div>
                    </div>

                    <!-- Colonnes de liens -->
                    <div class="flex flex-col justify-start gap-8 sm:flex-row md:justify-end md:gap-16">
                        <!-- Colonne Portfolio -->
                        <div class="flex flex-col justify-start gap-4">
                            <div class="text-xl font-bold text-black dark:text-gray-100">{{ t('footer.portfolio') }}</div>
                            <ul class="text-design-system-paragraph text-base font-normal">
                                <li class="mb-2 list-none">
                                    <Link :href="route('public.home')" :title="t('footer.home')">{{ t('footer.home') }}</Link>
                                </li>
                                <li class="mb-2 list-none">
                                    <Link :href="route('public.projects')" :title="t('footer.projects')">{{ t('footer.projects') }}</Link>
                                </li>
                                <li class="mb-2 list-none">
                                    <Link :href="route('public.certifications-career')" :title="t('footer.certifications-career')">{{
                                        t('footer.certifications-career')
                                    }}</Link>
                                </li>
                                <li class="mb-2 list-none">
                                    <Link :href="route('public.about')" :title="t('navigation.about')">{{ t('navigation.about') }}</Link>
                                </li>
                            </ul>
                        </div>

                        <!-- Colonne Blog -->
                        <div class="flex flex-col justify-start gap-4">
                            <div class="text-xl font-bold text-black dark:text-gray-100">{{ t('footer.blog') }}</div>
                            <ul class="text-design-system-paragraph text-base font-normal">
                                <li class="mb-2 list-none">
                                    <Link :href="route('public.blog.home')" :title="t('footer.blog_home')">{{ t('footer.blog_home') }}</Link>
                                </li>
                                <li class="mb-2 list-none">
                                    <Link :href="route('public.blog.index')" :title="t('footer.blog_articles')">{{ t('footer.blog_articles') }}</Link>
                                </li>
                                <li v-if="latestBlogPost" class="mb-2 list-none">
                                    <Link :href="route('public.blog.post', { slug: latestBlogPost.slug })" :title="t('footer.blog_latest')">{{
                                        t('footer.blog_latest')
                                    }}</Link>
                                </li>
                            </ul>
                        </div>

                        <!-- Colonne Me retrouver -->
                        <div class="flex flex-col justify-start gap-4">
                            <div class="text-xl font-bold text-black dark:text-gray-100">{{ t('footer.find_me') }}</div>
                            <ul class="text-design-system-paragraph text-base font-normal">
                                <li v-for="link in socialMediaLinks" :key="link.id" class="mb-2 list-none">
                                    <a :href="link.url" :title="link.name" target="_blank">
                                        <div class="flex items-center justify-start gap-2">
                                            <SvgIcon :svg="link.icon_svg" class="flex h-4 fill-black dark:fill-gray-100" />
                                            <span>{{ link.name }}</span>
                                        </div>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Copyright -->
        <div class="flex w-full flex-col items-center justify-start gap-2.5">
            <div class="container flex h-12 items-center justify-center px-4 text-center md:justify-start md:px-0 md:text-left">
                <div class="text-sm text-white md:text-base dark:text-gray-400">{{ t('footer.copyright') }}</div>
            </div>
        </div>
    </div>
</template>

<style scoped></style>
