<script setup lang="ts">
import LightShape from '@/components/public/LightShape.vue';
import ProjectHead from '@/components/public/ProjectPage/ProjectHead.vue';
import ContentSectionTitle from '@/components/public/Ui/ContentSectionTitle.vue';
import PublicAppLayout from '@/layouts/PublicAppLayout.vue';
import { SocialMediaLink, SSRFullCreation } from '@/types';
import { Head } from '@inertiajs/vue3';
import VueMarkdown from 'vue-markdown-render';

defineProps<{
    socialMediaLinks: SocialMediaLink[];
    creation: SSRFullCreation;
}>();

const activeSection = 'description';
const defaultSvgIcon =
    '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.5 2 2 6.5 2 12C2 17.5 6.5 22 12 22C17.5 22 22 17.5 22 12C22 6.5 17.5 2 12 2ZM12 20C7.59 20 4 16.41 4 12C4 7.59 7.59 4 12 4C16.41 4 20 7.59 20 12C20 16.41 16.41 20 12 20ZM12.5 7H11V13L16.2 16.2L17 14.9L12.5 12.2V7Z" fill="currentColor"></path></svg>';
</script>

<template>
    <Head title="Creation" />
    <PublicAppLayout :socialMediaLinks="socialMediaLinks">
        <div class="absolute top-0 left-0 z-0 h-full w-full overflow-hidden">
            <LightShape class="absolute top-0 left-[-27rem] xl:left-[-15rem]" />
        </div>

        <div class="z-10 container mb-16 flex flex-col gap-16">
            <ProjectHead :creation="creation" />

            <!-- Barre de navigation -->
            <div class="border-b border-gray-200">
                <div class="flex space-x-8">
                    <button
                        class="cursor-pointer border-b-2 py-4 text-xl transition-colors"
                        :class="activeSection === 'description' ? 'border-black text-black' : 'border-transparent text-gray-500 hover:text-black'"
                    >
                        Description
                    </button>
                    <button
                        class="cursor-pointer border-b-2 py-4 text-xl transition-colors"
                        :class="activeSection === 'features' ? 'border-black text-black' : 'border-transparent text-gray-500 hover:text-black'"
                        v-if="creation.features.length"
                    >
                        Fonctionnalités clés
                    </button>
                    <button
                        class="cursor-pointer border-b-2 py-4 text-xl transition-colors"
                        :class="activeSection === 'technologies' ? 'border-black text-black' : 'border-transparent text-gray-500 hover:text-black'"
                        v-if="creation.technologies.length"
                    >
                        Technologies utilisées
                    </button>
                    <button
                        class="cursor-pointer border-b-2 py-4 text-xl transition-colors"
                        :class="activeSection === 'screnshots' ? 'border-black text-black' : 'border-transparent text-gray-500 hover:text-black'"
                        v-if="creation.screenshots.length"
                    >
                        Capture d'écrans
                    </button>
                </div>
            </div>
            <!-- Fin de la barre de navigation -->

            <section id="description" class="flex flex-col gap-8">
                <ContentSectionTitle>Description</ContentSectionTitle>
                <vue-markdown class="markdown-view" :source="creation.fullDescription" />
            </section>

            <section id="features" class="flex flex-col gap-8">
                <ContentSectionTitle>Fonctionnalités clés</ContentSectionTitle>
                <div class="grid gap-16 md:grid-cols-2 lg:grid-cols-3">
                    <div v-for="feature in creation.features" :key="feature.id" class="flex flex-col gap-6">
                        <h3 class="text-design-system-paragraph text-xl font-bold">{{ feature.title }}</h3>
                        <div class="text-design-system-paragraph text-lg font-normal">{{ feature.description }}</div>
                    </div>
                </div>
            </section>

            <section id="technologies" class="flex flex-col gap-8">
                <ContentSectionTitle>Technologies utilisées</ContentSectionTitle>
                <div class="grid grid-cols-1 gap-3 self-stretch sm:grid-cols-2 lg:gap-4 xl:grid-cols-3">
                    <div
                        v-for="tech in creation.technologies"
                        :key="tech.name"
                        class="flex items-center justify-start gap-2 rounded-lg p-2 outline-1 outline-gray-200"
                    >
                        <div class="size-10 lg:size-16" v-html="tech.svgIcon || defaultSvgIcon"></div>
                        <div class="flex w-full flex-col justify-center gap-1">
                            <div class="text-design-system-title text-sm lg:text-base">{{ tech.name }}</div>
                            <div class="text-design-system-paragraph w-full justify-center text-xs lg:text-sm">
                                {{ tech.description }}
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </PublicAppLayout>
</template>
