import { useRoute } from '@/composables/useRoute';
import { BlogCategoryFilter, BlogTypeFilter, SSRSearchResult, SSRTechnology, Tag } from '@/types';
import axios from 'axios';
import debounce from 'lodash.debounce';
import { computed, ref, watch } from 'vue';

export function useSearch() {
    const route = useRoute();

    // State
    const searchQuery = ref('');
    const selectedTags = ref<number[]>([]);
    const selectedTechnologies = ref<number[]>([]);
    const selectedCategories = ref<number[]>([]);
    const selectedTypes = ref<string[]>([]);
    const searchResults = ref<SSRSearchResult[]>([]);
    const availableTags = ref<Tag[]>([]);
    const availableTechnologies = ref<SSRTechnology[]>([]);
    const availableCategories = ref<BlogCategoryFilter[]>([]);
    const availableTypes = ref<BlogTypeFilter[]>([]);
    const isLoading = ref(false);
    const searchCache = ref<Map<string, SSRSearchResult[]>>(new Map());

    // Computed
    const searchKey = computed(() => {
        return `${searchQuery.value}|${[...selectedTags.value].sort().join(',')}|${[...selectedTechnologies.value].sort().join(',')}|${[...selectedCategories.value].sort().join(',')}|${[...selectedTypes.value].sort().join(',')}`;
    });

    const hasActiveFilters = computed(() => {
        return (
            selectedTags.value.length > 0 ||
            selectedTechnologies.value.length > 0 ||
            selectedCategories.value.length > 0 ||
            selectedTypes.value.length > 0
        );
    });

    const filteredTags = computed(() => {
        if (!searchQuery.value) return availableTags.value;
        return availableTags.value.filter((tag) => tag.name.toLowerCase().includes(searchQuery.value.toLowerCase()));
    });

    const filteredTechnologies = computed(() => {
        if (!searchQuery.value) return availableTechnologies.value;
        return availableTechnologies.value.filter((tech) => tech.name.toLowerCase().includes(searchQuery.value.toLowerCase()));
    });

    // Methods
    const loadFilters = async () => {
        try {
            const response = await axios.get(route('public.search.filters'));
            availableTags.value = response.data.tags;
            availableTechnologies.value = response.data.technologies;
            availableCategories.value = response.data.blogCategories;
            availableTypes.value = response.data.blogTypes;
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
                    categories: selectedCategories.value,
                    types: selectedTypes.value,
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

    const toggleCategory = (categoryId: number) => {
        const index = selectedCategories.value.indexOf(categoryId);
        if (index > -1) {
            selectedCategories.value.splice(index, 1);
        } else {
            selectedCategories.value.push(categoryId);
        }
    };

    const toggleType = (type: string) => {
        const index = selectedTypes.value.indexOf(type);
        if (index > -1) {
            selectedTypes.value.splice(index, 1);
        } else {
            selectedTypes.value.push(type);
        }
    };

    const clearFilters = () => {
        selectedTags.value = [];
        selectedTechnologies.value = [];
        selectedCategories.value = [];
        selectedTypes.value = [];
    };

    const resetSearch = () => {
        searchQuery.value = '';
        clearFilters();
        searchResults.value = [];
    };

    // Debounced search
    const debouncedSearch = debounce(() => {
        void performSearch();
    }, 300);

    // Watch for changes
    watch([searchQuery, selectedTags, selectedTechnologies, selectedCategories, selectedTypes], () => {
        debouncedSearch();
    });

    return {
        // State
        searchQuery,
        selectedTags,
        selectedTechnologies,
        selectedCategories,
        selectedTypes,
        searchResults,
        availableTags,
        availableTechnologies,
        availableCategories,
        availableTypes,
        isLoading,

        // Computed
        hasActiveFilters,
        filteredTags,
        filteredTechnologies,

        // Methods
        loadFilters,
        performSearch,
        toggleTag,
        toggleTechnology,
        toggleCategory,
        toggleType,
        clearFilters,
        resetSearch,
    };
}
