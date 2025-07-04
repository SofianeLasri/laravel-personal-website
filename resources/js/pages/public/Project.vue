<script setup lang="ts">
import LightShape from '@/components/public/LightShape.vue';
import ProjectHead from '@/components/public/ProjectPage/ProjectHead.vue';
import ProjectScreenshotsContainer from '@/components/public/ProjectPage/ProjectScreenshotsContainer.vue';
import ProjectVideoGallery from '@/components/public/ProjectPage/ProjectVideoGallery.vue';
import TechnologyCard from '@/components/public/Technology/TechnologyCard.vue';
import ContentSectionTitle from '@/components/public/Ui/ContentSectionTitle.vue';
import HorizontalNavbar from '@/components/public/Ui/HorizontalNavbar.vue';
import { useTranslation } from '@/composables/useTranslation';
import PublicAppLayout from '@/layouts/PublicAppLayout.vue';
import { SocialMediaLink, SSRFullCreation } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { User } from 'lucide-vue-next';
import { ref } from 'vue';
import VueMarkdown from 'vue-markdown-render';

const props = defineProps<{
    socialMediaLinks: SocialMediaLink[];
    creation: SSRFullCreation;
}>();

const page = usePage();
const { t } = useTranslation();

const pageTitle = `${props.creation.name} - Sofiane Lasri`;
const pageDescription = props.creation.shortDescription;
const pageKeywords = `Sofiane Lasri, ${props.creation.name}, projet, développement web, ${props.creation.technologies.map((tech) => tech.name).join(', ')}`;
const pageUrl = page.props.ziggy.location;
const pageImage = props.creation.coverImage.jpg.large || '/opengraph-image-1200-630.jpg';
const pageImageAlt = `Image de couverture du projet ${props.creation.name}`;

const activeSection = ref('description');
const contentContainer = ref<HTMLElement | null>(null);

const sections = [{ id: 'description', label: t('project.description') }];

if (props.creation.features.length > 0) {
    sections.push({ id: 'features', label: t('project.key_features') });
}
if (props.creation.people.length > 0) {
    sections.push({ id: 'people', label: t('project.people_involved') });
}
if (props.creation.technologies.length > 0) {
    sections.push({ id: 'technologies', label: t('project.technologies_used') });
}
if (props.creation.videos.length > 0) {
    sections.push({ id: 'videos', label: t('project.videos') });
}
if (props.creation.screenshots.length > 0) {
    sections.push({ id: 'screenshots', label: t('project.screenshots') });
}
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
        <meta property="og:image" :content="pageImage" />
        <meta property="og:image:alt" :content="pageImageAlt" />

        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" :content="pageTitle" />
        <meta name="twitter:description" :content="pageDescription" />
        <meta name="twitter:image" :content="pageImage" />
        <meta name="twitter:image:alt" :content="pageImageAlt" />
    </Head>
    <PublicAppLayout :socialMediaLinks="socialMediaLinks">
        <div class="absolute top-0 left-0 z-0 h-full w-full overflow-hidden">
            <LightShape class="absolute top-0 left-[-27rem] xl:left-[-15rem]" />
        </div>

        <div class="z-10 container mb-16 flex flex-col gap-16 px-4">
            <ProjectHead :creation="creation" />

            <div ref="contentContainer" class="flex flex-col">
                <HorizontalNavbar
                    :items="sections"
                    v-model:activeItem="activeSection"
                    mode="auto"
                    :sticky="true"
                    :showArrows="true"
                    :containerRef="contentContainer"
                />

                <div class="content-sections mt-8">
                    <section id="description" class="flex flex-col gap-8">
                        <ContentSectionTitle>{{ t('project.description') }}</ContentSectionTitle>
                        <vue-markdown class="markdown-view" :source="creation.fullDescription" />
                    </section>

                    <section id="features" class="mt-16 flex flex-col gap-8" v-if="creation.features.length > 0">
                        <ContentSectionTitle>{{ t('project.key_features') }}</ContentSectionTitle>
                        <div class="grid gap-16 md:grid-cols-2 lg:grid-cols-3">
                            <div v-for="feature in creation.features" :key="feature.id" class="flex flex-col gap-6">
                                <h3 class="text-design-system-paragraph text-xl font-bold">{{ feature.title }}</h3>
                                <div class="text-design-system-paragraph text-lg font-normal">{{ feature.description }}</div>
                            </div>
                        </div>
                    </section>

                    <section id="people" class="mt-16 flex flex-col gap-8" v-if="creation.people.length > 0">
                        <ContentSectionTitle>{{ t('project.people_involved') }}</ContentSectionTitle>
                        <div class="grid grid-cols-1 gap-3 self-stretch sm:grid-cols-2 lg:gap-4 xl:grid-cols-3">
                            <div
                                class="flex items-center justify-center gap-2 rounded-lg bg-gray-100 p-2 outline-1 outline-gray-200"
                                v-for="person in creation.people"
                                :key="person.id"
                            >
                                <div class="flex size-10 flex-shrink-0 items-center justify-center overflow-hidden rounded-full lg:size-16">
                                    <img
                                        v-if="person.picture"
                                        :src="person.picture.webp.small"
                                        :alt="t('project.project_logo_alt')"
                                        class="h-full w-full object-cover"
                                        loading="lazy"
                                    />
                                    <User v-else class="text-design-system-paragraph size-6" />
                                </div>
                                <div class="flex w-full flex-col justify-center gap-1">
                                    <div class="text-design-system-title text-sm lg:text-base">{{ person.name }}</div>
                                    <div class="w-full justify-center text-xs lg:text-sm" v-if="person.url">
                                        <a :href="person.url" class="text-primary hover:underline">{{ person.url }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="technologies" class="mt-16 flex flex-col gap-8" v-if="creation.technologies.length > 0">
                        <ContentSectionTitle>{{ t('project.technologies_used') }}</ContentSectionTitle>
                        <div class="grid grid-cols-1 gap-3 self-stretch sm:grid-cols-2 lg:gap-4 xl:grid-cols-3">
                            <TechnologyCard
                                v-for="tech in creation.technologies"
                                :key="tech.name"
                                :name="tech.name"
                                :description="tech.description"
                                :iconPicture="tech.iconPicture"
                                class="bg-gray-100"
                            />
                        </div>
                    </section>

                    <section id="videos" class="mt-16 flex flex-col gap-8" v-if="creation.videos.length > 0">
                        <ContentSectionTitle>{{ t('project.videos') }}</ContentSectionTitle>
                        <ProjectVideoGallery :videos="creation.videos" />
                    </section>

                    <section id="screenshots" class="mt-16 flex flex-col gap-8" v-if="creation.screenshots.length > 0">
                        <ContentSectionTitle>{{ t('project.screenshots') }}</ContentSectionTitle>
                        <ProjectScreenshotsContainer :screenshots="creation.screenshots" />
                    </section>
                </div>
            </div>
        </div>
    </PublicAppLayout>
</template>
