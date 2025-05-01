<script setup lang="ts">
import LightShape from '@/components/public/LightShape.vue';
import ProjectCard from '@/components/public/ProjectCard.vue';
import ProjectFilter from '@/components/public/ProjectFilter.vue';
import SectionParagraph from '@/components/public/Ui/SectionParagraph.vue';
import SectionTitle from '@/components/public/Ui/SectionTitle.vue';
import PublicAppLayout from '@/layouts/PublicAppLayout.vue';
import { SocialMediaLink, SSRCreation, SSRTechnology } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type ProjectTab = 'development' | 'games' | 'source-engine';

const props = defineProps<{
    socialMediaLinks: SocialMediaLink[];
    creations: SSRCreation[];
    technologies: SSRTechnology[];
}>();

const frameworks = props.technologies.filter((tech) => tech.type === 'framework');
const libraries = props.technologies.filter((tech) => tech.type === 'library');
const gameEngines = props.technologies.filter((tech) => tech.type === 'game_engine');

const activeTab = ref<ProjectTab>('development');
const selectedFrameworks = ref<number[]>([]);
const selectedLibraries = ref<number[]>([]);
const selectedGameEngines = ref<number[]>([]);

const tabToCreationTypes = {
    development: ['portfolio', 'library', 'website', 'tool', 'other'],
    games: ['game'],
    'source-engine': ['map'],
};

watch(activeTab, () => {
    selectedFrameworks.value = [];
    selectedLibraries.value = [];
    selectedGameEngines.value = [];
});

const filteredCreations = computed(() => {
    const creationsByTab = props.creations.filter((creation) => tabToCreationTypes[activeTab.value].includes(creation.type));

    if (activeTab.value === 'source-engine') {
        return creationsByTab;
    }

    if (activeTab.value === 'development' && selectedFrameworks.value.length === 0 && selectedLibraries.value.length === 0) {
        return creationsByTab;
    }

    if (activeTab.value === 'games' && selectedGameEngines.value.length === 0) {
        return creationsByTab;
    }

    return creationsByTab.filter((creation) => {
        const techIds = creation.technologies.map((tech) => tech.id);

        if (activeTab.value === 'development') {
            const hasSelectedFramework = selectedFrameworks.value.length === 0 || selectedFrameworks.value.some((id) => techIds.includes(id));

            const hasSelectedLibrary = selectedLibraries.value.length === 0 || selectedLibraries.value.some((id) => techIds.includes(id));

            return hasSelectedFramework && hasSelectedLibrary;
        } else if (activeTab.value === 'games') {
            return selectedGameEngines.value.length === 0 || selectedGameEngines.value.some((id) => techIds.includes(id));
        }

        return true;
    });
});

const setActiveTab = (tab: ProjectTab) => {
    activeTab.value = tab;
};

const handleFrameworkFilterChange = (ids: number[]) => {
    selectedFrameworks.value = ids;
};

const handleLibraryFilterChange = (ids: number[]) => {
    selectedLibraries.value = ids;
};

const handleGameEngineFilterChange = (ids: number[]) => {
    selectedGameEngines.value = ids;
};
</script>

<template>
    <Head title="Projets" />
    <PublicAppLayout :socialMediaLinks="socialMediaLinks">
        <LightShape class="absolute top-0 left-[-27rem] z-0 xl:left-[-15rem]" />

        <div class="relative z-10 container mt-16 mb-16">
            <div class="mb-12 flex">
                <div class="flex flex-col gap-6">
                    <SectionTitle>Projects</SectionTitle>
                    <SectionParagraph>
                        Retrouvez tous mes projets et créations passés, allant du mapmaking sur Source Engine au développement web. :)
                    </SectionParagraph>
                </div>
                <div class="hidden xl:block"></div>
            </div>

            <div class="mb-8 border-b border-gray-200">
                <div class="flex space-x-8">
                    <button
                        @click="setActiveTab('development')"
                        class="border-b-2 py-4 text-xl transition-colors"
                        :class="activeTab === 'development' ? 'border-black text-black' : 'border-transparent text-gray-500 hover:text-black'"
                    >
                        Développement
                    </button>
                    <button
                        @click="setActiveTab('games')"
                        class="border-b-2 py-4 text-xl transition-colors"
                        :class="activeTab === 'games' ? 'border-black text-black' : 'border-transparent text-gray-500 hover:text-black'"
                    >
                        Jeux vidéos
                    </button>
                    <button
                        @click="setActiveTab('source-engine')"
                        class="border-b-2 py-4 text-xl transition-colors"
                        :class="activeTab === 'source-engine' ? 'border-black text-black' : 'border-transparent text-gray-500 hover:text-black'"
                    >
                        Source Engine
                    </button>
                </div>
            </div>

            <div class="flex flex-col gap-8 lg:flex-row">
                <div v-if="activeTab !== 'source-engine'" class="w-full space-y-6 lg:w-72">
                    <template v-if="activeTab === 'development'">
                        <ProjectFilter name="Framework" :technologies="frameworks" @filter-change="handleFrameworkFilterChange" />
                        <ProjectFilter name="Librairies" :technologies="libraries" @filter-change="handleLibraryFilterChange" />
                    </template>

                    <template v-else-if="activeTab === 'games'">
                        <ProjectFilter name="Moteurs de jeu" :technologies="gameEngines" @filter-change="handleGameEngineFilterChange" />
                    </template>
                </div>

                <div v-else class="w-full lg:w-72"></div>

                <div class="flex-1">
                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                        <div v-for="creation in filteredCreations" :key="creation.id">
                            <ProjectCard class="md:w-full" :creation="creation" />
                        </div>

                        <div v-if="filteredCreations.length === 0" class="col-span-full py-12 text-center">
                            <p class="text-lg text-gray-500">Aucun projet ne correspond à vos critères.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </PublicAppLayout>
</template>
