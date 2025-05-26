<script setup lang="ts">
import CertificationCard from '@/components/public/CertificationCard.vue';
import ExperienceCard from '@/components/public/ExperienceCard.vue';
import LightShape from '@/components/public/LightShape.vue';
import HeroSectionTitle from '@/components/public/Ui/HeroSectionTitle.vue';
import SectionParagraph from '@/components/public/Ui/SectionParagraph.vue';
import SectionTitle from '@/components/public/Ui/SectionTitle.vue';
import { useTranslation } from '@/composables/useTranslation';
import PublicAppLayout from '@/layouts/PublicAppLayout.vue';
import { SocialMediaLink, SSRCertification, SSRExperience } from '@/types';
import { Head } from '@inertiajs/vue3';

const { t } = useTranslation();

defineProps<{
    socialMediaLinks: SocialMediaLink[];
    certifications: SSRCertification[];
    educationExperiences: SSRExperience[];
    workExperiences: SSRExperience[];
}>();
</script>

<template>
    <Head title="Certifications & Parcours" />
    <PublicAppLayout :socialMediaLinks="socialMediaLinks">
        <div class="absolute top-0 left-0 z-0 h-full w-full overflow-hidden">
            <LightShape class="absolute top-0 left-[-27rem] xl:left-[-15rem]" />
        </div>

        <div class="relative z-10 container mt-16 mb-16 px-4">
            <div class="mb-12 flex">
                <div class="flex flex-1 flex-col gap-6">
                    <HeroSectionTitle>Certifications & Parcours.</HeroSectionTitle>
                    <SectionParagraph> Retrouvez mes certifications et mon parcours (scolaire et professionnel). </SectionParagraph>
                </div>
                <div class="hidden flex-1 xl:block"></div>
            </div>

            <div class="flex flex-col items-start justify-start gap-16">
                <div v-if="certifications.length > 0" class="flex w-full flex-col items-start justify-start gap-8">
                    <SectionTitle :title="t('career.certifications')" />
                    <div class="grid w-full grid-cols-1 gap-16 sm:grid-cols-2 lg:grid-cols-3">
                        <CertificationCard v-for="certification in certifications" :key="certification.id" :certification="certification" />
                    </div>
                </div>

                <div v-if="educationExperiences.length > 0" class="flex flex-col gap-8">
                    <SectionTitle :title="t('career.educational_path')" />
                    <div class="grid w-full grid-cols-1 gap-16 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <ExperienceCard v-for="experience in educationExperiences" :key="experience.id" :experience="experience" />
                    </div>
                </div>

                <div v-if="workExperiences.length > 0" class="flex flex-col gap-8">
                    <SectionTitle :title="t('career.professional_path')" />
                    <div class="grid w-full grid-cols-1 gap-16 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <ExperienceCard v-for="experience in workExperiences" :key="experience.id" :experience="experience" />
                    </div>
                </div>
            </div>
        </div>
    </PublicAppLayout>
</template>

<style scoped></style>
