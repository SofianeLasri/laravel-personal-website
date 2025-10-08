<script setup lang="ts">
import { useTranslation } from '@/composables/useTranslation';
import { BlogCategoryFilter, BlogTypeFilter, SSRTechnology, Tag } from '@/types';
import { ChevronDown, ChevronUp, Filter } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    availableTags: Tag[];
    availableTechnologies: SSRTechnology[];
    availableCategories: BlogCategoryFilter[];
    availableTypes: BlogTypeFilter[];
    selectedTags: number[];
    selectedTechnologies: number[];
    selectedCategories: number[];
    selectedTypes: string[];
}>();

const emit = defineEmits<{
    'toggle-tag': [id: number];
    'toggle-technology': [id: number];
    'toggle-category': [id: number];
    'toggle-type': [type: string];
    clear: [];
}>();

const { t } = useTranslation();
const showFilters = ref(false);
const showAllTags = ref(false);
const showAllTechnologies = ref(false);
const showAllCategories = ref(false);

const hasActiveFilters = computed(() => {
    return (
        props.selectedTags.length > 0 ||
        props.selectedTechnologies.length > 0 ||
        props.selectedCategories.length > 0 ||
        props.selectedTypes.length > 0
    );
});

const visibleTags = computed(() => {
    return showAllTags.value ? props.availableTags : props.availableTags.slice(0, 5);
});

const visibleTechnologies = computed(() => {
    return showAllTechnologies.value ? props.availableTechnologies : props.availableTechnologies.slice(0, 5);
});

const visibleCategories = computed(() => {
    return showAllCategories.value ? props.availableCategories : props.availableCategories.slice(0, 5);
});

const toggleFilters = () => {
    showFilters.value = !showFilters.value;
};

const handleTagToggle = (tagId: number) => {
    emit('toggle-tag', tagId);
};

const handleTechnologyToggle = (techId: number) => {
    emit('toggle-technology', techId);
};

const handleCategoryToggle = (categoryId: number) => {
    emit('toggle-category', categoryId);
};

const handleTypeToggle = (type: string) => {
    emit('toggle-type', type);
};

const handleClearFilters = () => {
    emit('clear');
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <button
            class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
            @click="toggleFilters"
        >
            <Filter class="h-4 w-4" />
            <span>{{ t('search.filters') }}</span>
            <span v-if="hasActiveFilters" class="bg-atomic-tangerine-400 rounded-full px-2 py-0.5 text-xs text-white">
                {{ selectedTags.length + selectedTechnologies.length + selectedCategories.length + selectedTypes.length }}
            </span>
            <component :is="showFilters ? ChevronUp : ChevronDown" class="ml-auto h-4 w-4" />
        </button>

        <Transition name="slide-down">
            <div v-if="showFilters" class="flex flex-col gap-4">
                <!-- Tags Section -->
                <div v-if="availableTags.length > 0" class="flex flex-col gap-2">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('search.tags') }}</h4>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="tag in visibleTags"
                            :key="tag.id"
                            class="rounded-full px-3 py-1 text-sm transition-colors"
                            :class="
                                selectedTags.includes(tag.id)
                                    ? 'bg-atomic-tangerine-400 hover:bg-atomic-tangerine-500 text-white'
                                    : 'bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                            "
                            @click="handleTagToggle(tag.id)"
                        >
                            {{ tag.name }}
                        </button>
                    </div>
                    <button
                        v-if="availableTags.length > 5"
                        class="text-atomic-tangerine-400 hover:text-atomic-tangerine-500 self-start text-sm"
                        @click="showAllTags = !showAllTags"
                    >
                        {{ showAllTags ? t('search.show_less') : t('search.show_more') }}
                    </button>
                </div>

                <!-- Technologies Section -->
                <div v-if="availableTechnologies.length > 0" class="flex flex-col gap-2">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('search.technologies') }}</h4>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="tech in visibleTechnologies"
                            :key="tech.id"
                            class="flex items-center gap-2 rounded-full px-3 py-1 text-sm transition-colors"
                            :class="
                                selectedTechnologies.includes(tech.id)
                                    ? 'bg-atomic-tangerine-400 hover:bg-atomic-tangerine-500 text-white'
                                    : 'bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                            "
                            @click="handleTechnologyToggle(tech.id)"
                        >
                            <img v-if="tech.iconPicture" :src="tech.iconPicture.webp.thumbnail" :alt="tech.name" class="h-4 w-4 object-contain" />
                            {{ tech.name }}
                        </button>
                    </div>
                    <button
                        v-if="availableTechnologies.length > 5"
                        class="text-atomic-tangerine-400 hover:text-atomic-tangerine-500 self-start text-sm"
                        @click="showAllTechnologies = !showAllTechnologies"
                    >
                        {{ showAllTechnologies ? t('search.show_less') : t('search.show_more') }}
                    </button>
                </div>

                <!-- Blog Categories Section -->
                <div v-if="availableCategories.length > 0" class="flex flex-col gap-2">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('search.categories') }}</h4>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="category in visibleCategories"
                            :key="category.id"
                            class="rounded-full px-3 py-1 text-sm transition-colors"
                            :class="
                                selectedCategories.includes(category.id)
                                    ? 'bg-atomic-tangerine-400 hover:bg-atomic-tangerine-500 text-white'
                                    : 'bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                            "
                            @click="handleCategoryToggle(category.id)"
                        >
                            {{ category.name }}
                        </button>
                    </div>
                    <button
                        v-if="availableCategories.length > 5"
                        class="text-atomic-tangerine-400 hover:text-atomic-tangerine-500 self-start text-sm"
                        @click="showAllCategories = !showAllCategories"
                    >
                        {{ showAllCategories ? t('search.show_less') : t('search.show_more') }}
                    </button>
                </div>

                <!-- Blog Types Section -->
                <div v-if="availableTypes.length > 0" class="flex flex-col gap-2">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('search.blog_types') }}</h4>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="type in availableTypes"
                            :key="type.value"
                            class="rounded-full px-3 py-1 text-sm transition-colors"
                            :class="
                                selectedTypes.includes(type.value)
                                    ? 'bg-atomic-tangerine-400 hover:bg-atomic-tangerine-500 text-white'
                                    : 'bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                            "
                            @click="handleTypeToggle(type.value)"
                        >
                            {{ type.label }}
                        </button>
                    </div>
                </div>

                <!-- Clear Filters -->
                <button v-if="hasActiveFilters" class="self-start text-sm text-red-500 hover:text-red-600" @click="handleClearFilters">
                    {{ t('search.clear_filters') }}
                </button>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.slide-down-enter-active,
.slide-down-leave-active {
    transition: all 0.3s ease;
}

.slide-down-enter-from,
.slide-down-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}
</style>
