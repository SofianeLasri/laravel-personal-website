<script setup lang="ts">
import LightShape from '@/components/public/LightShape.vue';
import BaseButton from '@/components/public/Ui/Button/BaseButton.vue';
import HeroSectionTitle from '@/components/public/Ui/HeroSectionTitle.vue';
import { useTranslation } from '@/composables/useTranslation';
import PublicAppLayout from '@/layouts/PublicAppLayout.vue';
import { SocialMediaLink } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import ArrowRightRegular from '@/components/font-awesome/ArrowRightRegular.vue';

const page = usePage();
const { t } = useTranslation();

defineProps<{
    socialMediaLinks: SocialMediaLink[];
}>();

const pageTitle = `${t('about.title')} - Sofiane Lasri`;
const pageDescription = t('about.subtitle');
const pageKeywords = 'Sofiane Lasri, développeur web, à propos, profil, portfolio, Laravel, Vue.js, Full Stack';
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

                    <!-- Hero Article Card -->
                    <div class="flex items-center gap-10">
                        <!-- Article Card -->
                        <div class="flex aspect-video h-96 shrink-0 overflow-hidden rounded-2xl shadow-[0px_0.25rem_0.5rem_0px_rgba(0,0,0,0.25)]">
                            <img class="h-full w-full object-cover" loading="eager" src="https://placehold.co/709x398" alt="Image de couverture" />
                        </div>

                        <div class="flex grow flex-col gap-4">
                            <div class="flex flex-col gap-3">
                                <h4 class="text-design-system-title text-4xl font-bold">
                                    Découvrez comment logger toutes les requêtes entrante de votre application Laravel !
                                </h4>

                                <div class="flex gap-4">
                                    <!-- Category Badge -->
                                    <div class="bg-atomic-tangerine-100 flex items-center justify-center gap-2.5 rounded-lg px-2 py-1">
                                        <div class="text-atomic-tangerine-800 justify-center">Astuce</div>
                                    </div>

                                    <div class="text-design-system-paragraph flex items-center">1er septembre 2025</div>
                                </div>

                                <p class="text-design-system-paragraph">
                                    Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce consequat purus et auctor gravida. Praesent
                                    tristique quis odio vitae mattis. Mauris tristique urna sed nibh consectetur, sed dignissim enim rhoncus. Morbi
                                    congue lacinia viverra. Sed malesuada fermentum rhoncus. Sed tempus finibus ullamcorper. In efficitur magna orci,
                                    ut gravida ante ultricies non. Phasellus ante est, volutpat ut neque at, ornare cursus eros. Curabitur arcu nulla,
                                    interdum a gravida convallis, tempor quis tortor. Quisque pretium at risus quis iaculis.
                                </p>

                                <div>
                                    <BaseButton variant="black">Lire l'article</BaseButton>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mb-16 flex flex-col gap-4">
                <h3 class="text-design-system-title text-2xl font-bold">Articles récents</h3>

                <!-- Articles Grid -->
                <div class="grid grid-cols-4">
                    <!-- Article Card Placeholder -->
                    <div class="flex flex-col gap-4">
                        <!-- Article Card -->
                        <div class="relative aspect-video w-full overflow-hidden rounded-2xl shadow-[0px_0.25rem_0.5rem_0px_rgba(0,0,0,0.25)]">
                            <!-- div to make the badge over the background image -->
                            <div class="absolute z-10 flex h-full w-full p-2">
                                <!-- div to not make the badge ultra wide -->
                                <div>
                                    <!-- Category Badge -->
                                    <div class="bg-atomic-tangerine-100 flex items-center justify-center gap-2.5 rounded-lg px-2 py-1">
                                        <div class="text-atomic-tangerine-800 justify-center">Astuce</div>
                                    </div>
                                </div>
                            </div>
                            <img class="h-full w-full object-cover" loading="lazy" src="https://placehold.co/709x398" alt="Image de couverture" />
                        </div>

                        <div class="flex flex-col gap-0.5">
                            <h4 class="text-design-system-title text-xl font-bold">Un avis (pas objectif) sur DiRT 3 :)</h4>
                            <div class="text-design-system-paragraph text-sm">31 aout 2025</div>
                        </div>

                        <p class="text-design-system-paragraph">
                            Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce consequat purus et auctor gravida. Praesent tristique quis
                            odio vitae mattis. Mauris tristique urna sed nibh consectetur, sed dignissim enim rhoncus. Morbi congue lacinia viverra.
                        </p>

                        <div class="flex w-full items-end">
                            <!-- View Article Button -->
                            <div class="flex flex-col">
                                <div class="flex items-center gap-1">
                                    <div class="text-bold">Lire l'article</div>
                                    <ArrowRightRegular class="size-3 fill-black" />
                                </div>
                                <!-- Underline, should be transparent if button not hovered -->
                                <div class="bg-primary h-1"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </PublicAppLayout>
</template>
