<script setup lang="ts">
import Logo from '@/components/public/Logo.vue';
import { useTranslation } from '@/composables/useTranslation';
import { SocialMediaLink } from '@/types';
import { Link } from '@inertiajs/vue3';

defineProps<{
    socialMediaLinks: SocialMediaLink[];
}>();

const { t } = useTranslation();
</script>

<template>
    <div class="relative flex flex-col overflow-hidden bg-black">
        <div
            class="border-border flex w-full flex-col items-center justify-start gap-2.5 rounded-br-2xl rounded-bl-2xl border-t bg-gray-100 py-8 md:py-16"
        >
            <div class="container space-y-8 px-4 md:space-y-0">
                <!-- Section principale du footer -->
                <div class="flex flex-col gap-8 md:flex-row md:items-center md:justify-between md:gap-4">
                    <!-- Logo et informations -->
                    <div class="flex flex-col gap-4">
                        <Logo class="h-8" />
                        <div class="flex flex-col justify-start">
                            <div class="text-xl font-bold text-black">{{ t('footer.name') }}</div>
                            <div class="text-design-system-paragraph text-base font-normal">{{ t('footer.title') }}</div>
                        </div>
                    </div>

                    <!-- Colonnes de liens -->
                    <div class="flex flex-col justify-start gap-8 sm:flex-row md:justify-end md:gap-16">
                        <!-- Colonne Portfolio -->
                        <div class="flex flex-col justify-start gap-4">
                            <div class="text-xl font-bold text-black">{{ t('footer.portfolio') }}</div>
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
                                <!--                                <li class="mb-2 list-none">
                                    <a href="/about" :title="t('footer.about_me')">{{ t('footer.about_me') }}</a>
                                </li>-->
                            </ul>
                        </div>

                        <!-- Colonne Me retrouver -->
                        <div class="flex flex-col justify-start gap-4">
                            <div class="text-xl font-bold text-black">{{ t('footer.find_me') }}</div>
                            <ul class="text-design-system-paragraph text-base font-normal">
                                <li class="mb-2 list-none" v-for="link in socialMediaLinks" :key="link.id">
                                    <a :href="link.url" :title="link.name" target="_blank">
                                        <div class="flex items-center justify-start gap-2">
                                            <div class="flex h-4 fill-black" v-html="link.icon_svg"></div>
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
                <div class="text-sm text-white md:text-base">{{ t('footer.copyright') }}</div>
            </div>
        </div>
    </div>
</template>

<style scoped></style>
