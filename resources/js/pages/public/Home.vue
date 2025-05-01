<script setup lang="ts">
import ArrowUpRightRegular from '@/components/font-awesome/ArrowUpRightRegular.vue';
import CareerContainer from '@/components/public/Career/CareerContainer.vue';
import HeroSection from '@/components/public/HeroSection.vue';
import LightShape from '@/components/public/LightShape.vue';
import ProjectCard from '@/components/public/ProjectCard.vue';
import ProjectSlider from '@/components/public/ProjectSlider.vue';
import TechnologyExperiencesContainer from '@/components/public/Technology/TechnologyExperiencesContainer.vue';
import BlackButton from '@/components/public/Ui/Button/BlackButton.vue';
import SectionParagraph from '@/components/public/ui/SectionParagraph.vue';
import SectionTitle from '@/components/public/ui/SectionTitle.vue';
import LaravelLogo from '@/components/shapes/LaravelLogo.vue';
import { useTranslation } from '@/composables/useTranslation';
import PublicAppLayout from '@/layouts/PublicAppLayout.vue';
import { SocialMediaLink, SSRCreation, SSRExperience, SSRTechnologyExperience } from '@/types';
import { Head } from '@inertiajs/vue3';

const { t } = useTranslation();

defineProps<{
    socialMediaLinks: SocialMediaLink[];
    yearsOfExperience: number;
    developmentCreationsCount: number;
    technologiesCount: number;
    laravelCreations: SSRCreation[];
    technologyExperiences: SSRTechnologyExperience[];
    experiences: SSRExperience[];
}>();
</script>

<template>
    <Head title="Accueil" />
    <PublicAppLayout :socialMediaLinks="socialMediaLinks">
        <LightShape class="absolute top-0 left-[-27rem] z-0 xl:left-[-15rem]" />
        <HeroSection
            :socialMediaLinks="socialMediaLinks"
            :yearsOfExperience="yearsOfExperience"
            :developmentCreationsCount="developmentCreationsCount"
            :technologiesCount="technologiesCount"
        />

        <LightShape class="absolute top-[40rem] right-[-27rem] z-0 xl:right-[-15rem]" />

        <div class="container mt-16 mb-16 flex flex-col gap-32" id="backend-and-laravel-specialization-section">
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
                        <SectionTitle>
                            <span>{{ t('home.backend.title_part1') }}</span>
                            <span class="ml-2 text-[#FF2D20]">{{ t('home.backend.title_part2') }}</span>
                        </SectionTitle>
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
                    <div class="flex flex-1 flex-col gap-8">
                        <SectionTitle>{{ t('home.laravel-section.title') }}</SectionTitle>
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
                    <div>
                        <BlackButton>
                            <span>{{ t('home.laravel-section.view_other_projects') }}</span>
                            <ArrowUpRightRegular class="h-4 fill-white" />
                        </BlackButton>
                    </div>
                </div>
            </section>

            <section class="flex flex-col items-center gap-16" id="other-skills-section">
                <div class="inline-flex max-w-[56rem] flex-col items-center gap-8 text-center">
                    <SectionTitle>{{ t('home.other_skills.title') }}</SectionTitle>
                    <SectionParagraph>
                        {{ t('home.other_skills.description') }}
                    </SectionParagraph>
                </div>
                <TechnologyExperiencesContainer :experiences="technologyExperiences" />
            </section>

            <section class="flex flex-col items-center gap-16" id="career-section">
                <div class="flex">
                    <div class="flex flex-1 flex-col gap-8">
                        <SectionTitle>{{ t('home.career.title') }}</SectionTitle>
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
