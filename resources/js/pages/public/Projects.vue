<script setup lang="ts">
import LightShape from '@/components/public/LightShape.vue';
import ProjectCard from '@/components/public/ProjectCard.vue';
import ProjectFilter from '@/components/public/ProjectFilter.vue';
import HeroSectionTitle from '@/components/public/Ui/HeroSectionTitle.vue';
import HorizontalNavbar from '@/components/public/Ui/HorizontalNavbar.vue';
import SectionParagraph from '@/components/public/Ui/SectionParagraph.vue';
import PublicAppLayout from '@/layouts/PublicAppLayout.vue';
import { SocialMediaLink, SSRSimplifiedCreation, SSRTechnology } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

type ProjectTab = 'development' | 'games' | 'source-engine';

const props = defineProps<{
    socialMediaLinks: SocialMediaLink[];
    creations: SSRSimplifiedCreation[];
    technologies: SSRTechnology[];
}>();

const containerKey = computed(() => {
    return `${activeTab.value}-${selectedFrameworks.value.join('-')}-${selectedLibraries.value.join('-')}-${selectedGameEngines.value.join('-')}-${selectedLanguages.value.join('-')}`;
});

const frameworks = props.technologies.filter((tech) => tech.type === 'framework');
const libraries = props.technologies.filter((tech) => tech.type === 'library');
const gameEngines = props.technologies.filter((tech) => tech.type === 'game_engine');
const languages = props.technologies.filter((tech) => tech.type === 'language');

const parseUrlParams = () => {
    const params = new URLSearchParams(window.location.search);
    const tab = params.get('tab') as ProjectTab | null;

    if (tab && ['development', 'games', 'source-engine'].includes(tab)) {
        activeTab.value = tab;
    }

    const frameworksParam = params.get('frameworks');
    const librariesParam = params.get('libraries');
    const gameEnginesParam = params.get('gameEngines');
    const languagesParam = params.get('languages');

    if (frameworksParam) {
        selectedFrameworks.value = frameworksParam
            .split(',')
            .map(Number)
            .filter((id) => frameworks.some((tech) => tech.id === id));
    }

    if (librariesParam) {
        selectedLibraries.value = librariesParam
            .split(',')
            .map(Number)
            .filter((id) => libraries.some((tech) => tech.id === id));
    }

    if (gameEnginesParam) {
        selectedGameEngines.value = gameEnginesParam
            .split(',')
            .map(Number)
            .filter((id) => gameEngines.some((tech) => tech.id === id));
    }

    if (languagesParam) {
        selectedLanguages.value = languagesParam
            .split(',')
            .map(Number)
            .filter((id) => languages.some((tech) => tech.id === id));
    }
};

const updateUrlParams = () => {
    const params = new URLSearchParams();

    params.set('tab', activeTab.value);

    if (selectedFrameworks.value.length > 0) {
        params.set('frameworks', selectedFrameworks.value.join(','));
    }

    if (selectedLibraries.value.length > 0) {
        params.set('libraries', selectedLibraries.value.join(','));
    }

    if (selectedGameEngines.value.length > 0) {
        params.set('gameEngines', selectedGameEngines.value.join(','));
    }

    if (selectedLanguages.value.length > 0) {
        params.set('languages', selectedLanguages.value.join(','));
    }

    const newUrl = `${window.location.pathname}?${params.toString()}`;
    window.history.pushState({ path: newUrl }, '', newUrl);
};

const activeTab = ref<ProjectTab>('development');
const selectedFrameworks = ref<number[]>([]);
const selectedLibraries = ref<number[]>([]);
const selectedGameEngines = ref<number[]>([]);
const selectedLanguages = ref<number[]>([]);
const isParsingUrlParams = ref(false);

const handlePopState = () => {
    isParsingUrlParams.value = true;
    parseUrlParams();
    setTimeout(() => {
        isParsingUrlParams.value = false;
    }, 0);
};

onMounted(() => {
    isParsingUrlParams.value = true;

    setTimeout(() => {
        parseUrlParams();

        setTimeout(() => {
            isParsingUrlParams.value = false;
        }, 0);
    }, 0);

    window.addEventListener('popstate', handlePopState);
});

onBeforeUnmount(() => {
    window.removeEventListener('popstate', handlePopState);
});

const navItems = [
    { id: 'development', label: 'Développement' },
    { id: 'games', label: 'Jeux vidéos' },
    { id: 'source-engine', label: 'Source Engine' },
];

const tabToCreationTypes = {
    development: ['portfolio', 'library', 'website', 'tool', 'other'],
    games: ['game'],
    'source-engine': ['map'],
};

watch(activeTab, () => {
    if (!isParsingUrlParams.value) {
        selectedFrameworks.value = [];
        selectedLibraries.value = [];
        selectedGameEngines.value = [];
        selectedLanguages.value = [];
    }
    updateUrlParams();
});

const filteredCreations = computed(() => {
    const creationsByTab = props.creations.filter((creation) => tabToCreationTypes[activeTab.value].includes(creation.type));

    if (activeTab.value === 'source-engine') {
        return creationsByTab;
    }

    if (
        activeTab.value === 'development' &&
        selectedFrameworks.value.length === 0 &&
        selectedLibraries.value.length === 0 &&
        selectedLanguages.value.length === 0
    ) {
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
            const hasSelectedLanguage = selectedLanguages.value.length === 0 || selectedLanguages.value.some((id) => techIds.includes(id));
            return hasSelectedFramework && hasSelectedLibrary && hasSelectedLanguage;
        } else if (activeTab.value === 'games') {
            return selectedGameEngines.value.length === 0 || selectedGameEngines.value.some((id) => techIds.includes(id));
        }

        return true;
    });
});

const handleFrameworkFilterChange = (ids: number[]) => {
    selectedFrameworks.value = ids;
    updateUrlParams();
};

const handleLibraryFilterChange = (ids: number[]) => {
    selectedLibraries.value = ids;
    updateUrlParams();
};

const handleGameEngineFilterChange = (ids: number[]) => {
    selectedGameEngines.value = ids;
    updateUrlParams();
};

const handleLanguageFilterChange = (ids: number[]) => {
    selectedLanguages.value = ids;
    updateUrlParams();
};
</script>

<template>
    <Head title="Projets" />
    <PublicAppLayout :socialMediaLinks="socialMediaLinks">
        <div class="absolute top-0 left-0 z-0 h-full w-full overflow-hidden">
            <LightShape class="absolute top-0 left-[-27rem] xl:left-[-15rem]" />
        </div>

        <div class="relative z-10 container mt-16 mb-16 px-4">
            <div class="mb-12 flex">
                <div class="flex flex-1 flex-col gap-6">
                    <HeroSectionTitle>Projects</HeroSectionTitle>
                    <SectionParagraph>
                        Retrouvez tous mes projets et créations passés, allant du mapmaking sur Source Engine au développement web. :)
                    </SectionParagraph>
                </div>
                <div class="hidden flex-1 xl:block"></div>
            </div>

            <div class="mb-8">
                <HorizontalNavbar :items="navItems" v-model:activeItem="activeTab" mode="manual" :sticky="false" :showArrows="false" />
            </div>

            <div class="flex flex-col gap-8 lg:flex-row">
                <div v-if="activeTab !== 'source-engine'" class="w-full space-y-6 lg:w-72">
                    <template v-if="activeTab === 'development'">
                        <ProjectFilter
                            name="Framework"
                            :technologies="frameworks"
                            :initial-selected-filters="selectedFrameworks"
                            @filter-change="handleFrameworkFilterChange"
                        />
                        <ProjectFilter
                            name="Librairies"
                            :technologies="libraries"
                            :initial-selected-filters="selectedLibraries"
                            @filter-change="handleLibraryFilterChange"
                        />
                        <ProjectFilter
                            name="Langages"
                            :technologies="languages"
                            :initial-selected-filters="selectedLanguages"
                            @filter-change="handleLanguageFilterChange"
                        />
                    </template>

                    <template v-else-if="activeTab === 'games'">
                        <ProjectFilter
                            name="Moteurs de jeu"
                            :technologies="gameEngines"
                            :initial-selected-filters="selectedGameEngines"
                            @filter-change="handleGameEngineFilterChange"
                        />
                    </template>
                </div>

                <div v-else class="w-full lg:w-72"></div>

                <div class="flex-1">
                    <Transition name="projects-fade" mode="out-in">
                        <div :key="containerKey">
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <div v-for="creation in filteredCreations" :key="creation.id">
                                    <ProjectCard class="md:w-full" :creation="creation" />
                                </div>

                                <div v-if="filteredCreations.length === 0" class="col-span-full py-12 text-center">
                                    <p class="text-lg text-gray-500">Aucun projet ne correspond à vos critères.</p>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </div>
    </PublicAppLayout>
</template>

<style scoped>
.projects-fade-enter-active,
.projects-fade-leave-active {
    transition:
        opacity 0.3s ease,
        transform 0.3s ease;
}

.projects-fade-enter-from,
.projects-fade-leave-to {
    opacity: 0;
    transform: translateY(0.5rem);
}
</style>
