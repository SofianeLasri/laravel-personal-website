<script setup lang="ts">
import SearchFilters from '@/components/public/Search/SearchFilters.vue';
import SearchInput from '@/components/public/Search/SearchInput.vue';
import SearchResults from '@/components/public/Search/SearchResults.vue';
import { useSearch } from '@/composables/useSearch';
import { useTranslation } from '@/composables/useTranslation';
import { X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps<{
    isOpen: boolean;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useTranslation();

const {
    searchQuery,
    selectedTags,
    selectedTechnologies,
    searchResults,
    availableTags,
    availableTechnologies,
    isLoading,
    hasActiveFilters,
    loadFilters,
    toggleTag,
    toggleTechnology,
    clearFilters,
    resetSearch,
} = useSearch();

const hasQuery = computed(() => searchQuery.value.length > 0 || hasActiveFilters.value);

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

const handleProjectSelect = () => {
    closeModal();
};

watch(
    () => props.isOpen,
    (isOpen) => {
        if (isOpen) {
            document.body.style.overflow = 'hidden';
            loadFilters();
        } else {
            document.body.style.overflow = '';
            resetSearch();
        }
    },
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
                        <!-- Header -->
                        <div class="flex flex-col gap-4 border-b p-6 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <h2 class="text-design-system-title text-2xl font-bold" id="searchModalTitle">
                                    {{ t('search.search') }}
                                </h2>
                                <button
                                    @click="closeModal"
                                    class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 transition-colors hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700"
                                    :aria-label="t('search.close_search')"
                                >
                                    <X class="h-5 w-5" />
                                </button>
                            </div>

                            <!-- Search Input -->
                            <SearchInput v-model="searchQuery" :loading="isLoading" :autofocus="true" />

                            <!-- Filters -->
                            <SearchFilters
                                :available-tags="availableTags"
                                :available-technologies="availableTechnologies"
                                :selected-tags="selectedTags"
                                :selected-technologies="selectedTechnologies"
                                @toggle-tag="toggleTag"
                                @toggle-technology="toggleTechnology"
                                @clear="clearFilters"
                            />
                        </div>

                        <!-- Results -->
                        <div class="flex-1 overflow-y-auto p-6">
                            <SearchResults :results="searchResults" :loading="isLoading" :has-query="hasQuery" @select="handleProjectSelect" />
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
</style>
