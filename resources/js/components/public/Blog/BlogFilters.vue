<script setup lang="ts">
import BlogCategoryFilter from './BlogCategoryFilter.vue';
import BlogSortDropdown from './BlogSortDropdown.vue';
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

interface Props {
    categories: Array<{
        id: number;
        name: string;
        slug: string;
        color: string;
        postCount?: number;
    }>;
    currentFilters: {
        category?: string | string[];
        sort?: string;
    };
}

const props = defineProps<Props>();

// Convert single values to arrays for consistency
const selectedCategories = ref<string[]>(
    Array.isArray(props.currentFilters.category)
        ? props.currentFilters.category
        : props.currentFilters.category
          ? [props.currentFilters.category]
          : [],
);

const selectedSort = ref(props.currentFilters.sort || 'newest');

const hasActiveFilters = computed(() => {
    return selectedCategories.value.length > 0;
});

const applyFilters = () => {
    const params: any = {};

    if (selectedCategories.value.length > 0) {
        params.category = selectedCategories.value.join(',');
    }
    if (selectedSort.value && selectedSort.value !== 'newest') {
        params.sort = selectedSort.value;
    }

    router.get('/blog/articles', params, {
        preserveState: true,
        preserveScroll: true,
    });
};

const handleCategoryFilterChange = (categories: string[]) => {
    selectedCategories.value = categories;
    applyFilters();
};

const handleSortChange = (sort: string) => {
    selectedSort.value = sort;
    applyFilters();
};

const clearAllFilters = () => {
    selectedCategories.value = [];
    applyFilters();
};

// Watch for prop changes
watch(
    () => props.currentFilters,
    (newFilters) => {
        selectedCategories.value = Array.isArray(newFilters.category) ? newFilters.category : newFilters.category ? [newFilters.category] : [];

        selectedSort.value = newFilters.sort || 'newest';
    },
    { deep: true },
);
</script>

<template>
    <div class="w-full space-y-6">
        <!-- Category Filter -->
        <BlogCategoryFilter
            name="Catégories"
            :categories="categories"
            :initial-selected-filters="selectedCategories"
            @filter-change="handleCategoryFilterChange"
        />

        <!-- Sort Filter -->
        <BlogSortDropdown :current-sort="selectedSort" @sort-change="handleSortChange" />

        <!-- Clear Filters Button -->
        <button
            v-if="hasActiveFilters"
            class="w-full rounded-lg bg-gray-200 px-4 py-2 text-center hover:bg-gray-300 dark:bg-gray-800 dark:hover:bg-gray-700"
            @click="clearAllFilters"
        >
            Effacer tous les filtres
        </button>
    </div>
</template>