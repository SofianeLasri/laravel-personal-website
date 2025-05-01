<script setup lang="ts">
import LightShape from '@/components/public/LightShape.vue';
import ProjectCard from '@/components/public/ProjectCard.vue';
import ProjectFilter from '@/components/public/ProjectFilter.vue';
import SectionParagraph from '@/components/public/Ui/SectionParagraph.vue';
import SectionTitle from '@/components/public/Ui/SectionTitle.vue';
import { useTranslation } from '@/composables/useTranslation';
import PublicAppLayout from '@/layouts/PublicAppLayout.vue';
import { SocialMediaLink, SSRCreation } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const { t } = useTranslation();

// Définition des types pour les filtres
type ProjectTab = 'development' | 'games' | 'source-engine';
type FilterState = 'active' | 'hovered' | 'inactive';
type FilterCategory = 'framework' | 'library';

const props = defineProps<{
    socialMediaLinks: SocialMediaLink[];
    creations: SSRCreation[];
}>();

// État pour les filtres actifs
const activeTab = ref<ProjectTab>('development');
const frameworkFilter = ref<FilterState | null>('active');
const libraryFilter = ref<FilterState | null>(null);

// Compter le nombre de projets pour chaque filtre
const filterCounts = computed(() => {
    const counts = {
        framework: { active: 0, hovered: 0, inactive: 0 },
        library: { active: 0, hovered: 0, inactive: 0 },
    };

    props.creations.forEach((creation) => {
        // Pour les frameworks
        if (creation.technologies.some((t) => t.name.toLowerCase().includes('framework'))) {
            if (!creation.endedAt) {
                counts.framework.active++;
            } else {
                counts.framework.inactive++;
            }
            counts.framework.hovered = 4; // Valeur fixe pour l'exemple
        }

        // Pour les librairies
        if (creation.technologies.some((t) => t.name.toLowerCase().includes('library'))) {
            if (!creation.endedAt) {
                counts.library.active++;
            } else {
                counts.library.inactive++;
            }
            counts.library.hovered = 4; // Valeur fixe pour l'exemple
        }
    });

    return counts;
});

// Filtrer les créations en fonction de l'onglet actif et des filtres
const filteredCreations = computed(() => {
    let filtered = [...props.creations];

    // Filtrer par onglet
    if (activeTab.value === 'development') {
        filtered = filtered.filter((creation) => ['portfolio', 'website', 'library', 'tool'].includes(creation.type.toLowerCase()));
    } else if (activeTab.value === 'games') {
        filtered = filtered.filter((creation) => ['game'].includes(creation.type.toLowerCase()));
    } else if (activeTab.value === 'source-engine') {
        filtered = filtered.filter((creation) => creation.technologies.some((tech) => tech.name.toLowerCase().includes('source')));
    }

    // Appliquer le filtre de framework
    if (frameworkFilter.value) {
        filtered = applyFilter(filtered, 'framework', frameworkFilter.value);
    }

    // Appliquer le filtre de librairie
    if (libraryFilter.value) {
        filtered = applyFilter(filtered, 'library', libraryFilter.value);
    }

    return filtered;
});

function applyFilter(creations: SSRCreation[], category: FilterCategory, state: FilterState) {
    return creations.filter((creation) => {
        const hasTechnology = creation.technologies.some((tech) => tech.name.toLowerCase().includes(category));

        if (!hasTechnology) return false;

        if (state === 'active' && !creation.endedAt) {
            return true;
        }

        if (state === 'inactive' && creation.endedAt) {
            return true;
        }

        if (state === 'hovered') {
            // Implémentation fictive pour "hovered"
            return true;
        }

        return false;
    });
}

// Changer l'onglet actif
const setActiveTab = (tab: ProjectTab) => {
    activeTab.value = tab;
};

// Modifier le filtre pour une catégorie
const toggleFrameworkFilter = (state: FilterState) => {
    frameworkFilter.value = frameworkFilter.value === state ? null : state;
};

const toggleLibraryFilter = (state: FilterState) => {
    libraryFilter.value = libraryFilter.value === state ? null : state;
};
</script>

<template>
    <Head title="Projets" />
    <PublicAppLayout :socialMediaLinks="socialMediaLinks">
        <LightShape class="absolute top-0 left-[-27rem] z-0 xl:left-[-15rem]" />

        <div class="relative z-10 container mt-16 mb-16">
            <div class="mb-12 flex">
                <div class="flex flex-1 flex-col gap-6">
                    <SectionTitle>Projects</SectionTitle>
                    <SectionParagraph>
                        Retrouvez tous mes projets et créations passés, allant du mapmaking sur Source Engine au développement web. :)
                    </SectionParagraph>
                </div>
                <div class="hidden flex-1 xl:block"></div>
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

            <!-- Conteneur principal -->
            <div class="flex flex-col gap-8 lg:flex-row">
                <!-- Filtres sur la gauche -->
                <div class="w-full space-y-6 lg:w-72">
                    <ProjectFilter />
                </div>

                <!-- Grille de projets -->
                <div class="flex-1">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div v-for="creation in filteredCreations" :key="creation.id" class="h-full">
                            <ProjectCard :creation="creation" />
                        </div>

                        <!-- Message si aucun projet ne correspond aux filtres -->
                        <div v-if="filteredCreations.length === 0" class="col-span-full py-12 text-center">
                            <p class="text-lg text-gray-500">Aucun projet ne correspond à vos critères.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <LightShape class="absolute top-[40rem] right-[-27rem] z-0 xl:right-[-15rem]" />
    </PublicAppLayout>
</template>
