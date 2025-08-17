<script setup lang="ts">
import MagnifyingGlassRegular from '@/components/font-awesome/MagnifyingGlassRegular.vue';
import BlackButton from '@/components/public/Ui/Button/BlackButton.vue';
import { useTranslation } from '@/composables/useTranslation';
import { SSRSimplifiedCreation, SSRTechnology, Tag } from '@/types';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import debounce from 'lodash.debounce';
import { X } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute } from '@/composables/useRoute';

const props = defineProps<{
    isOpen: boolean;
}>();
const route = useRoute();

const { t } = useTranslation();

const emit = defineEmits<{
    close: [];
}>();

const searchInput = ref<HTMLInputElement | null>(null);
const searchQuery = ref('');
const selectedTags = ref<number[]>([]);
const selectedTechnologies = ref<number[]>([]);
const searchResults = ref<SSRSimplifiedCreation[]>([]);
const availableTags = ref<Tag[]>([]);
const availableTechnologies = ref<SSRTechnology[]>([]);
const isLoading = ref(false);
const showFilters = ref(false);
const showAllTags = ref(false);
const showAllTechnologies = ref(false);

const searchCache = ref<Map<string, any>>(new Map());

const focusSearchInput = async () => {
    await nextTick();
    searchInput.value?.focus();
};

watch(
    () => props.isOpen,
    (isOpen) => {
        if (isOpen) {
            document.body.style.overflow = 'hidden';
            focusSearchInput();
            loadFilters();
        } else {
            document.body.style.overflow = '';
            resetSearch();
        }
    },
);

// Computed
const searchKey = computed(() => {
    return `${searchQuery.value}|${[...selectedTags.value].sort().join(',')}|${[...selectedTechnologies.value].sort().join(',')}`;
});

const filteredTags = computed(() => {
    if (!searchQuery.value) return availableTags.value;
    return availableTags.value.filter((tag) => tag.name.toLowerCase().includes(searchQuery.value.toLowerCase()));
});

const filteredTechnologies = computed(() => {
    if (!searchQuery.value) return availableTechnologies.value;
    return availableTechnologies.value.filter((tech) => tech.name.toLowerCase().includes(searchQuery.value.toLowerCase()));
});

const hasActiveFilters = computed(() => {
    return selectedTags.value.length > 0 || selectedTechnologies.value.length > 0;
});

const closeModal = () => {
    emit('close');
};

const handleEscape = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        closeModal();
    }
};

const handleBackdropClick = (event: MouseEvent) => {
    if (event.target === event.currentTarget) {
        closeModal();
    }
};

const resetSearch = () => {
    searchQuery.value = '';
    selectedTags.value = [];
    selectedTechnologies.value = [];
    searchResults.value = [];
    showFilters.value = false;
    showAllTags.value = false;
    showAllTechnologies.value = false;
};

const loadFilters = async () => {
    try {
        const response = await axios.get(route('public.search.filters'));
        availableTags.value = response.data.tags;
        availableTechnologies.value = response.data.technologies;
    } catch (error) {
        console.error('Failed to load search filters:', error);
    }
};

const performSearch = async () => {
    const key = searchKey.value;

    if (searchCache.value.has(key)) {
        searchResults.value = searchCache.value.get(key);
        return;
    }

    if (!searchQuery.value && !hasActiveFilters.value) {
        searchResults.value = [];
        return;
    }

    isLoading.value = true;

    try {
        const response = await axios.get(route('public.search'), {
            params: {
                q: searchQuery.value,
                tags: selectedTags.value,
                technologies: selectedTechnologies.value,
            },
        });

        searchResults.value = response.data.results;

        searchCache.value.set(key, response.data.results);
    } catch (error) {
        console.error('Search failed:', error);
        searchResults.value = [];
    } finally {
        isLoading.value = false;
    }
};

const toggleTag = (tagId: number) => {
    const index = selectedTags.value.indexOf(tagId);
    if (index > -1) {
        selectedTags.value.splice(index, 1);
    } else {
        selectedTags.value.push(tagId);
    }
};

const toggleTechnology = (techId: number) => {
    const index = selectedTechnologies.value.indexOf(techId);
    if (index > -1) {
        selectedTechnologies.value.splice(index, 1);
    } else {
        selectedTechnologies.value.push(techId);
    }
};

const clearFilters = () => {
    selectedTags.value = [];
    selectedTechnologies.value = [];
};

const goToProject = (slug: string) => {
    closeModal();
    router.visit(route('public.projects.show', { slug }));
};

watch(
    [searchQuery, selectedTags, selectedTechnologies],
    debounce(() => {
        performSearch();
    }, 300),
);

onMounted(() => {
    document.addEventListener('keydown', handleEscape);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleEscape);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition name="search-modal">
            <div
                v-if="isOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-gray-200/25 backdrop-blur-sm"
                @click="handleBackdropClick"
                role="dialog"
                aria-labelledby="searchModalTitle"
            >
                <div
                    class="bg-action-container-outer-color action-container-outer-shadow action-container-outer-border action-container-background-blur mx-4 w-full max-w-4xl overflow-hidden rounded-3xl p-2"
                >
                    <div
                        class="action-container-inner-shadow flex max-h-[80vh] flex-col overflow-hidden rounded-2xl border bg-white dark:border-gray-700 dark:bg-gray-900"
                    >
                        <!-- Header with search input -->
                        <div class="flex flex-col gap-4 border-b p-6">
                            <div class="flex items-center justify-between">
                                <h2 class="text-design-system-title text-2xl font-bold" id="searchModalTitle">{{ t('search.search') }}</h2>
                                <button
                                    @click="closeModal"
                                    class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 transition-colors hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700"
                                    :aria-label="t('search.close_search')"
                                >
                                    <X class="h-5 w-5" />
                                </button>
                            </div>

                            <!-- Search input -->
                            <div class="relative flex items-center gap-4 rounded-full bg-gray-100 pe-6 dark:bg-gray-800">
                                <BlackButton class="w-12">
                                    <MagnifyingGlassRegular class="absolute size-4 fill-white dark:fill-gray-900" />
                                </BlackButton>
                                <input
                                    ref="searchInput"
                                    type="text"
                                    :placeholder="t('search.search_placeholder')"
                                    v-model="searchQuery"
                                    maxlength="255"
                                    class="w-full border-none bg-transparent py-4 pr-4 text-lg focus:outline-none"
                                    data-form-type="query"
                                />
                            </div>

                            <!-- Filter toggle -->
                            <div class="flex items-center justify-between">
                                <button
                                    @click="showFilters = !showFilters"
                                    class="text-design-system-paragraph flex items-center gap-2 text-sm font-medium transition-colors hover:text-gray-900 dark:hover:text-gray-100"
                                >
                                    {{ showFilters ? t('search.hide_filters') : t('search.show_filters') }}
                                    <span class="transform transition-transform" :class="{ 'rotate-180': showFilters }"> ▼ </span>
                                </button>
                                <button v-if="hasActiveFilters" @click="clearFilters" class="text-primary text-sm font-medium hover:underline">
                                    {{ t('search.clear_filters') }}
                                </button>
                            </div>
                        </div>

                        <!-- Filters section -->
                        <Transition name="slide-down">
                            <div v-if="showFilters" class="max-h-80 overflow-y-auto border-b bg-gray-50 p-6 dark:border-gray-700 dark:bg-gray-800">
                                <div class="flex flex-col gap-6">
                                    <!-- Tags -->
                                    <div>
                                        <div class="mb-3 flex items-center justify-between">
                                            <h3 class="text-design-system-title text-sm font-semibold">{{ t('search.tags') }}</h3>
                                            <button
                                                v-if="filteredTags.length > 12"
                                                @click="showAllTags = !showAllTags"
                                                class="text-primary text-xs font-medium hover:underline"
                                            >
                                                {{ showAllTags ? t('search.see_less') : `${t('search.see_all')} (${filteredTags.length})` }}
                                            </button>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                v-for="tag in showAllTags ? filteredTags : filteredTags.slice(0, 12)"
                                                :key="tag.id"
                                                @click="toggleTag(tag.id)"
                                                class="rounded-full border px-3 py-1 text-sm transition-colors"
                                                :class="
                                                    selectedTags.includes(tag.id)
                                                        ? 'bg-primary border-primary text-white'
                                                        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                                                "
                                            >
                                                {{ tag.name }}
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Technologies -->
                                    <div>
                                        <div class="mb-3 flex items-center justify-between">
                                            <h3 class="text-design-system-title text-sm font-semibold">{{ t('search.technologies') }}</h3>
                                            <button
                                                v-if="filteredTechnologies.length > 12"
                                                @click="showAllTechnologies = !showAllTechnologies"
                                                class="text-primary text-xs font-medium hover:underline"
                                            >
                                                {{
                                                    showAllTechnologies
                                                        ? t('search.see_less')
                                                        : `${t('search.see_all')} (${filteredTechnologies.length})`
                                                }}
                                            </button>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                v-for="tech in showAllTechnologies ? filteredTechnologies : filteredTechnologies.slice(0, 12)"
                                                :key="tech.id"
                                                @click="toggleTechnology(tech.id)"
                                                class="flex items-center gap-2 rounded-full border px-3 py-1 text-sm transition-colors"
                                                :class="
                                                    selectedTechnologies.includes(tech.id)
                                                        ? 'bg-primary border-primary text-white'
                                                        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                                                "
                                            >
                                                <img
                                                    :src="tech.iconPicture.webp.thumbnail"
                                                    :alt="`${tech.name} icon`"
                                                    class="h-4 w-4 object-contain"
                                                    loading="lazy"
                                                />
                                                {{ tech.name }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </Transition>

                        <!-- Results section -->
                        <div class="flex-1 overflow-y-auto p-6">
                            <!-- Loading state -->
                            <div v-if="isLoading" class="flex items-center justify-center py-12">
                                <div class="text-design-system-paragraph">{{ t('search.searching') }}</div>
                            </div>

                            <!-- No query state -->
                            <div v-else-if="!searchQuery && !hasActiveFilters" class="flex flex-col items-center justify-center py-12 text-center">
                                <MagnifyingGlassRegular class="mb-4 h-12 w-12 fill-gray-400" />
                                <h3 class="text-design-system-title mb-2 text-lg font-semibold">{{ t('search.start_search') }}</h3>
                                <p class="text-design-system-paragraph">{{ t('search.start_search_description') }}</p>
                            </div>

                            <!-- No results -->
                            <div v-else-if="searchResults.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
                                <div class="text-design-system-paragraph mb-2 text-lg font-semibold">{{ t('search.no_results') }}</div>
                                <p class="text-design-system-paragraph">{{ t('search.no_results_description') }}</p>
                            </div>

                            <!-- Results -->
                            <div v-else>
                                <div class="text-design-system-paragraph mb-4 text-sm">
                                    {{ searchResults.length }} {{ searchResults.length > 1 ? t('search.results') : t('search.result') }}
                                    {{ searchResults.length > 1 ? t('search.found_plural') : t('search.found_singular') }}
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <button
                                        v-for="project in searchResults"
                                        :key="project.id"
                                        @click="goToProject(project.slug)"
                                        class="group dark:hover:bg-gray-750 flex cursor-pointer items-start gap-4 rounded-lg border bg-white p-4 text-left transition-all hover:bg-gray-50 hover:shadow-md dark:border-gray-700 dark:bg-gray-800"
                                    >
                                        <!-- Project logo -->
                                        <div
                                            class="flex h-16 w-16 flex-shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-gray-50 dark:border-gray-700 dark:bg-gray-800"
                                        >
                                            <picture class="h-full w-full">
                                                <source :srcset="project.logo.webp.small" type="image/webp" />
                                                <img
                                                    :src="project.logo.avif.small"
                                                    :alt="t('search.project_logo_alt', { name: project.name })"
                                                    class="h-full w-full object-cover"
                                                    loading="lazy"
                                                />
                                            </picture>
                                        </div>

                                        <!-- Project info -->
                                        <div class="min-w-0 flex-1">
                                            <h3 class="text-design-system-title group-hover:text-primary mb-1 font-semibold">
                                                {{ project.name }}
                                            </h3>
                                            <p class="text-design-system-paragraph mb-2 text-sm">{{ project.type }}</p>
                                            <p class="text-design-system-paragraph line-clamp-2 text-sm">
                                                {{ project.shortDescription }}
                                            </p>

                                            <!-- Technologies -->
                                            <div v-if="project.technologies.length > 0" class="mt-3 flex flex-wrap gap-1">
                                                <span
                                                    v-for="tech in project.technologies.slice(0, 3)"
                                                    :key="tech.id"
                                                    class="inline-flex items-center gap-1 rounded bg-gray-100 px-2 py-1 text-xs"
                                                >
                                                    <img
                                                        :src="tech.iconPicture.webp.thumbnail"
                                                        :alt="`${tech.name} icon`"
                                                        class="h-3 w-3 object-contain"
                                                        loading="lazy"
                                                    />
                                                    {{ tech.name }}
                                                </span>
                                                <span
                                                    v-if="project.technologies.length > 3"
                                                    class="inline-flex items-center rounded bg-gray-100 px-2 py-1 text-xs"
                                                >
                                                    +{{ project.technologies.length - 3 }}
                                                </span>
                                            </div>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.search-modal-enter-active,
.search-modal-leave-active {
    transition: opacity 0.3s ease;
}

.search-modal-enter-from,
.search-modal-leave-to {
    opacity: 0;
}

.slide-down-enter-active,
.slide-down-leave-active {
    transition: all 0.3s ease;
    overflow: hidden;
}

.slide-down-enter-from,
.slide-down-leave-to {
    max-height: 0;
    opacity: 0;
}

.slide-down-enter-to,
.slide-down-leave-from {
    max-height: 500px;
    opacity: 1;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
