<script setup lang="ts">
import LightShape from '@/components/public/LightShape.vue';
import TechnologyCard from '@/components/public/Technology/TechnologyCard.vue';
import ContentSectionTitle from '@/components/public/Ui/ContentSectionTitle.vue';
import { useTranslation } from '@/composables/useTranslation';
import PublicAppLayout from '@/layouts/PublicAppLayout.vue';
import { SocialMediaLink } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { Building, CalendarDays, MapPin } from 'lucide-vue-next';
import VueMarkdown from 'vue-markdown-render';

interface Experience {
    id: number;
    title: string;
    organizationName: string;
    logo: any;
    location: string;
    websiteUrl: string | null;
    shortDescription: string;
    fullDescription: string;
    technologies: any[];
    type: string;
    startedAt: string;
    endedAt: string | null;
    startedAtFormatted: string;
    endedAtFormatted: string | null;
}

const props = defineProps<{
    socialMediaLinks: SocialMediaLink[];
    experience: Experience;
}>();

const page = usePage();
const { t } = useTranslation();

const pageTitle = `${props.experience.title} - ${props.experience.organizationName} - Sofiane Lasri`;
const pageDescription = props.experience.shortDescription;
const pageKeywords = `Sofiane Lasri, ${props.experience.organizationName}, ${props.experience.title}, expérience professionnelle, ${props.experience.technologies.map((tech) => tech.name).join(', ')}`;
const pageUrl = page.props.ziggy.location;

const formatPeriod = () => {
    if (props.experience.endedAtFormatted) {
        return `${props.experience.startedAtFormatted} - ${props.experience.endedAtFormatted}`;
    }
    return `${props.experience.startedAtFormatted} - ${t('experience.present')}`;
};
</script>

<template>
    <Head :title="pageTitle">
        <!-- SEO Meta Tags -->
        <meta name="description" :content="pageDescription" />
        <meta name="keywords" :content="pageKeywords" />
        <meta name="robots" content="index, follow" />

        <!-- Open Graph -->
        <meta property="og:type" content="article" />
        <meta property="og:title" :content="pageTitle" />
        <meta property="og:description" :content="pageDescription" />
        <meta property="og:url" :content="pageUrl" />

        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary" />
        <meta name="twitter:title" :content="pageTitle" />
        <meta name="twitter:description" :content="pageDescription" />
    </Head>
    <PublicAppLayout :socialMediaLinks="socialMediaLinks">
        <div class="absolute top-0 left-0 z-0 h-full w-full overflow-hidden">
            <LightShape class="absolute top-0 left-[-27rem] xl:left-[-15rem]" />
        </div>

        <div class="z-10 container mb-16 flex flex-col gap-16 px-4">
            <!-- Header Section -->
            <div class="flex flex-col gap-8 pt-8">
                <div class="flex flex-col gap-6">
                    <!-- Organization Logo and Info -->
                    <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
                        <div v-if="experience.logo" class="mx-auto flex-shrink-0 sm:mx-0">
                            <div
                                class="flex size-24 items-center justify-center rounded-lg border bg-white p-4 shadow-[0px_0.25rem_0.5rem_0px_rgba(0,0,0,0.25)]"
                            >
                                <img
                                    :src="experience.logo.webp.small"
                                    :alt="`Logo ${experience.organizationName}`"
                                    class="h-full w-full object-contain"
                                    loading="lazy"
                                />
                            </div>
                        </div>
                        <div class="flex flex-grow flex-col gap-4">
                            <div>
                                <h1 class="text-design-system-title text-center text-3xl font-bold sm:text-left lg:text-4xl">
                                    {{ experience.title }}
                                </h1>
                                <div class="mt-2 flex items-center justify-center gap-2 sm:justify-start">
                                    <Building class="text-design-system-paragraph size-5" />
                                    <span class="text-design-system-paragraph text-lg font-medium">
                                        {{ experience.organizationName }}
                                    </span>
                                    <a
                                        v-if="experience.websiteUrl"
                                        :href="experience.websiteUrl"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-primary ml-2 hover:underline"
                                    >
                                        {{ experience.websiteUrl }}
                                    </a>
                                </div>
                            </div>

                            <!-- Meta Information -->
                            <div class="text-design-system-paragraph flex flex-wrap justify-center gap-4 sm:justify-start">
                                <div class="flex items-center gap-2">
                                    <CalendarDays class="size-4" />
                                    <span class="text-sm">{{ formatPeriod() }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <MapPin class="size-4" />
                                    <span class="text-sm">{{ experience.location }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Short Description -->
                    <div class="text-design-system-paragraph text-lg leading-relaxed">
                        {{ experience.shortDescription }}
                    </div>
                </div>
            </div>

            <!-- Content Sections -->
            <div class="flex flex-col gap-16">
                <!-- Description Section -->
                <section class="flex flex-col gap-8">
                    <ContentSectionTitle>{{ t('experience.description') }}</ContentSectionTitle>
                    <vue-markdown class="markdown-view" :source="experience.fullDescription" />
                </section>

                <!-- Technologies Section -->
                <section v-if="experience.technologies.length > 0" class="flex flex-col gap-8">
                    <ContentSectionTitle>{{ t('experience.technologies_used') }}</ContentSectionTitle>
                    <div class="grid grid-cols-1 gap-3 self-stretch sm:grid-cols-2 lg:gap-4 xl:grid-cols-3">
                        <TechnologyCard
                            v-for="tech in experience.technologies"
                            :key="tech.name"
                            :name="tech.name"
                            :description="tech.description"
                            :iconPicture="tech.iconPicture"
                            class="bg-gray-100"
                        />
                    </div>
                </section>
            </div>
        </div>
    </PublicAppLayout>
</template>
