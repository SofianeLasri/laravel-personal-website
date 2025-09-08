<script setup lang="ts">
import LightShape from '@/components/public/LightShape.vue';
import ProjectCard from '@/components/public/ProjectCard.vue';
import ProjectFilter from '@/components/public/ProjectFilter.vue';
import HeroSectionTitle from '@/components/public/Ui/HeroSectionTitle.vue';
import HorizontalNavbar from '@/components/public/Ui/HorizontalNavbar.vue';
import SectionParagraph from '@/components/public/Ui/SectionParagraph.vue';
import { useTranslation } from '@/composables/useTranslation';
import PublicAppLayout from '@/layouts/PublicAppLayout.vue';
import { SocialMediaLink, SSRSimplifiedCreation, SSRTechnology } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

type ProjectTab = 'development' | 'games' | 'source-engine';

const props = defineProps<{
    socialMediaLinks: SocialMediaLink[];
    creations: SSRSimplifiedCreation[];
    technologies: SSRTechnology[];
}>();

const page = usePage();
const { t } = useTranslation();

const pageTitle = 'Projets - Sofiane Lasri';
const pageDescription = `Découvrez mes ${props.creations.length} projets.`;
const pageKeywords = 'projets, portfolio, Sofiane Lasri, développement web, Laravel, Vue.js, réalisations, applications web';
const pageUrl = page.props.ziggy.location;
const pageImage = '/opengraph-image-1200-630.jpg';

const containerKey = computed(() => {
    return `${activeTab.value}-${selectedFrameworks.value.join('-')}-${selectedLibraries.value.join('-')}-${selectedGameEngines.value.join('-')}-${selectedLanguages.value.join('-')}`;
});

const frameworks = props.technologies.filter((tech) => tech.type === 'framework');
const libraries = props.technologies.filter((tech) => tech.type === 'library');
const gameEngines = props.technologies.filter((tech) => tech.type === 'game_engine');
const languages = props.technologies.filter((tech) => tech.type === 'language');

const parseUrlParams = () => {
    if (typeof window === 'undefined') return;

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
    if (typeof window === 'undefined') return;

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

    if (typeof window !== 'undefined') {
        const newUrl = `${window.location.pathname}?${params.toString()}`;
        window.history.pushState({ path: newUrl }, '', newUrl);
    }
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

    if (typeof window !== 'undefined') {
        window.addEventListener('popstate', handlePopState);
    }
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('popstate', handlePopState);
    }
});

const navItems = [
    { id: 'development', label: t('projects.development') },
    { id: 'games', label: t('projects.games') },
    { id: 'source-engine', label: t('projects.source_engine') },
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

const clearAllFilters = () => {
    selectedFrameworks.value = [];
    selectedLibraries.value = [];
    selectedGameEngines.value = [];
    selectedLanguages.value = [];
    updateUrlParams();
};
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
        </div>

        <div class="relative z-10 container mt-16 mb-16 px-4">
            <div class="mb-12 flex">
                <div class="flex flex-1 flex-col gap-6">
                    <HeroSectionTitle>{{ t('projects.title') }}</HeroSectionTitle>
                    <SectionParagraph>
                        {{ t('projects.description') }}
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
                            :name="t('projects.framework')"
                            :technologies="frameworks"
                            :initial-selected-filters="selectedFrameworks"
                            @filter-change="handleFrameworkFilterChange"
                        />
                        <ProjectFilter
                            :name="t('projects.libraries')"
                            :technologies="libraries"
                            :initial-selected-filters="selectedLibraries"
                            @filter-change="handleLibraryFilterChange"
                        />
                        <ProjectFilter
                            :name="t('projects.languages')"
                            :technologies="languages"
                            :initial-selected-filters="selectedLanguages"
                            @filter-change="handleLanguageFilterChange"
                        />
                    </template>

                    <template v-else-if="activeTab === 'games'">
                        <ProjectFilter
                            :name="t('projects.game_engines')"
                            :technologies="gameEngines"
                            :initial-selected-filters="selectedGameEngines"
                            @filter-change="handleGameEngineFilterChange"
                        />
                    </template>

                    <button
                        v-if="
                            (activeTab === 'development' &&
                                (selectedFrameworks.length > 0 || selectedLibraries.length > 0 || selectedLanguages.length > 0)) ||
                            (activeTab === 'games' && selectedGameEngines.length > 0)
                        "
                        @click="clearAllFilters"
                        data-testid="clear-filters-button"
                        class="w-full rounded-lg bg-gray-200 px-4 py-2 text-center hover:bg-gray-300 dark:bg-gray-800 dark:hover:bg-gray-700"
                    >
                        {{ t('projects.clear_filters') }}
                    </button>
                </div>

                <div v-else class="w-full lg:w-72"></div>

                <div class="flex-1">
                    <Transition name="projects-fade" mode="out-in">
                        <div :key="containerKey">
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2" data-testid="project-cards-container">
                                <div v-for="creation in filteredCreations" :key="creation.id" data-testid="project-card">
                                    <ProjectCard class="md:w-full" :creation="creation" />
                                </div>

                                <div v-if="filteredCreations.length === 0" class="col-span-full py-12 text-center" data-testid="no-projects-message">
                                    <p class="text-lg text-gray-500">{{ t('projects.no_projects') }}</p>
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
