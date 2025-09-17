import { useRoute } from '@/composables/useRoute';
import { SSRSimplifiedCreation, SSRTechnology, Tag } from '@/types';
import axios from 'axios';
import debounce from 'lodash.debounce';
import { computed, ref, watch } from 'vue';

export function useSearch() {
    const route = useRoute();

    // State
    const searchQuery = ref('');
    const selectedTags = ref<number[]>([]);
    const selectedTechnologies = ref<number[]>([]);
    const searchResults = ref<SSRSimplifiedCreation[]>([]);
    const availableTags = ref<Tag[]>([]);
    const availableTechnologies = ref<SSRTechnology[]>([]);
    const isLoading = ref(false);
    const searchCache = ref<Map<string, any>>(new Map());

    // Computed
    const searchKey = computed(() => {
        return `${searchQuery.value}|${[...selectedTags.value].sort().join(',')}|${[...selectedTechnologies.value].sort().join(',')}`;
    });

    const hasActiveFilters = computed(() => {
        return selectedTags.value.length > 0 || selectedTechnologies.value.length > 0;
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
    watch([searchQuery, selectedTags, selectedTechnologies], () => {
        debouncedSearch();
    });

    return {
        // State
        searchQuery,
        selectedTags,
        selectedTechnologies,
        searchResults,
        availableTags,
        availableTechnologies,
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
        clearFilters,
        resetSearch,
    };
}
