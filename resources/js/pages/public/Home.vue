<script setup lang="ts">
import ArrowUpRightRegular from '@/components/font-awesome/ArrowUpRightRegular.vue';
import CareerContainer from '@/components/public/Career/CareerContainer.vue';
import HeroSection from '@/components/public/HeroSection.vue';
import LightShape from '@/components/public/LightShape.vue';
import ProjectCard from '@/components/public/ProjectCard.vue';
import ProjectSlider from '@/components/public/ProjectSlider.vue';
import TechnologyExperiencesContainer from '@/components/public/Technology/TechnologyExperiencesContainer.vue';
import BlackLinkButton from '@/components/public/Ui/Button/BlackLinkButton.vue';
import HeroSectionTitle from '@/components/public/Ui/HeroSectionTitle.vue';
import SectionParagraph from '@/components/public/Ui/SectionParagraph.vue';
import Cube from '@/components/shapes/cube.vue';
import LaravelLogo from '@/components/shapes/LaravelLogo.vue';
import { useTranslation } from '@/composables/useTranslation';
import PublicAppLayout from '@/layouts/PublicAppLayout.vue';
import { SocialMediaLink, SSRExperience, SSRSimplifiedCreation, SSRTechnologyExperience } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { useRoute } from '@/composables/useRoute';

const page = usePage();
const route = useRoute();
const { t } = useTranslation();

const props = defineProps<{
    socialMediaLinks: SocialMediaLink[];
    yearsOfExperience: number;
    developmentCreationsCount: number;
    masteredFrameworksCount: number;
    laravelCreations: SSRSimplifiedCreation[];
    technologyExperiences: SSRTechnologyExperience[];
    experiences: SSRExperience[];
}>();

const pageTitle = 'Sofiane Lasri - Développeur Full Stack';
const pageDescription =
    'Portfolio de Sofiane Lasri, développeur Full-Stack spécialisé sur le framework Laravel, avec plus de ' +
    props.yearsOfExperience +
    " ans d'expérience dans le développement web.";
const pageKeywords =
    'Sofiane Lasri, Sofiane Lasri-Trienpont, développeur web, Laravel, Vue.js, PHP, JavaScript, Full-Stack, portfolio, développement web';
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
    <PublicAppLayout :socialMediaLinks="socialMediaLinks">
        <div class="absolute top-0 left-0 z-0 h-full w-full overflow-hidden">
            <LightShape class="absolute top-0 left-[-27rem] xl:left-[-15rem]" />
            <LightShape class="absolute top-[40rem] right-[-27rem] xl:right-[-15rem]" />
            <LightShape class="absolute top-[80rem] left-[-27rem] xl:left-[-15rem]" />
            <LightShape class="absolute top-[140rem] right-[-27rem] xl:right-[-15rem]" />
            <LightShape class="absolute bottom-[40rem] left-[-27rem] xl:left-[-15rem]" />
        </div>

        <HeroSection
            :socialMediaLinks="socialMediaLinks"
            :yearsOfExperience="yearsOfExperience"
            :developmentCreationsCount="developmentCreationsCount"
            :masteredFrameworksCount="masteredFrameworksCount"
        />

        <div class="z-10 container mt-16 mb-16 flex flex-col gap-32 px-4" id="backend-and-laravel-specialization-section">
            <section class="flex">
                <div class="hidden flex-1 items-center justify-center xl:flex">
                    <div class="h-64 w-60 overflow-hidden">
                        <LaravelLogo class="size-60" />
                    </div>
                    <!--                    <Cube class="motion-preset-oscillate motion-duration-5000 absolute top-[-27px] left-[61px]" />
                    <Cube class="motion-preset-oscillate motion-duration-5000 absolute top-[341px] left-[586px]" />-->
                </div>
                <div class="flex flex-1 flex-col gap-8">
                    <div class="flex items-center gap-4">
                        <LaravelLogo class="size-12 xl:hidden" />
                        <HeroSectionTitle>
                            <span>{{ t('home.backend.title_part1') }}</span>
                            <span class="ml-2 text-[#FF2D20]">{{ t('home.backend.title_part2') }}</span>
                        </HeroSectionTitle>
                    </div>

                    <div>
                        <SectionParagraph>
                            {{ t('home.backend.paragraph1') }}
                        </SectionParagraph>
                        <SectionParagraph>
                            {{ t('home.backend.paragraph2') }}
                        </SectionParagraph>
                        <SectionParagraph>
                            {{ t('home.backend.paragraph3') }}
                        </SectionParagraph>
                    </div>
                </div>
            </section>

            <section class="flex flex-col gap-16" id="laravel-section">
                <div class="flex">
                    <div class="relative flex flex-1 flex-col gap-8">
                        <Cube class="motion-preset-oscillate motion-duration-5000 absolute top-[128px] left-[-80px]" />
                        <HeroSectionTitle>{{ t('home.laravel-section.title') }}</HeroSectionTitle>
                        <SectionParagraph>
                            {{ t('home.laravel-section.paragraph') }}
                        </SectionParagraph>
                    </div>
                    <div class="hidden flex-1 xl:block"></div>
                </div>

                <div class="flex flex-col gap-8">
                    <ProjectSlider :items="laravelCreations">
                        <ProjectCard v-for="creation in laravelCreations" :key="creation.id" :creation="creation" />
                    </ProjectSlider>
                    <div class="w-fit">
                        <BlackLinkButton :href="route('public.projects')">
                            <span>{{ t('home.laravel-section.view_other_projects') }}</span>
                            <ArrowUpRightRegular class="h-4 fill-white" />
                        </BlackLinkButton>
                    </div>
                </div>
            </section>

            <section class="flex flex-col items-center gap-16" id="other-skills-section">
                <div class="relative inline-flex max-w-[56rem] flex-col items-center gap-8 text-center">
                    <Cube class="motion-preset-oscillate motion-duration-5000 absolute top-[8px] left-[-80px]" />
                    <HeroSectionTitle>{{ t('home.other_skills.title') }}</HeroSectionTitle>
                    <SectionParagraph>
                        {{ t('home.other_skills.description') }}
                    </SectionParagraph>
                </div>
                <TechnologyExperiencesContainer :experiences="technologyExperiences" />
            </section>

            <section class="flex flex-col items-center gap-16" id="career-section">
                <div class="flex">
                    <div class="relative flex flex-1 flex-col gap-8">
                        <Cube class="motion-preset-oscillate motion-duration-5000 absolute top-[0px] right-[-64px] hidden lg:block" />
                        <HeroSectionTitle>{{ t('home.career.title') }}</HeroSectionTitle>
                        <SectionParagraph>
                            {{ t('home.career.description') }}
                        </SectionParagraph>
                    </div>
                    <div class="hidden flex-1 xl:block"></div>
                </div>
                <CareerContainer :experience="experiences" />
            </section>
        </div>
    </PublicAppLayout>
</template>
