<script setup lang="ts">
import { useTranslation } from '@/composables/useTranslation';
import { SSRTechnology, Tag } from '@/types';
import { ChevronDown, ChevronUp, Filter } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    availableTags: Tag[];
    availableTechnologies: SSRTechnology[];
    selectedTags: number[];
    selectedTechnologies: number[];
}>();

const emit = defineEmits<{
    'toggle-tag': [id: number];
    'toggle-technology': [id: number];
    clear: [];
}>();

const { t } = useTranslation();
const showFilters = ref(false);
const showAllTags = ref(false);
const showAllTechnologies = ref(false);

const hasActiveFilters = computed(() => {
    return props.selectedTags.length > 0 || props.selectedTechnologies.length > 0;
});

const visibleTags = computed(() => {
    return showAllTags.value ? props.availableTags : props.availableTags.slice(0, 5);
});

const visibleTechnologies = computed(() => {
    return showAllTechnologies.value ? props.availableTechnologies : props.availableTechnologies.slice(0, 5);
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

const handleClearFilters = () => {
    emit('clear');
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <button
            @click="toggleFilters"
            class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
        >
            <Filter class="h-4 w-4" />
            <span>{{ t('search.filters') }}</span>
            <span v-if="hasActiveFilters" class="bg-atomic-tangerine-400 rounded-full px-2 py-0.5 text-xs text-white">
                {{ selectedTags.length + selectedTechnologies.length }}
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
                            @click="handleTagToggle(tag.id)"
                            class="rounded-full px-3 py-1 text-sm transition-colors"
                            :class="
                                selectedTags.includes(tag.id)
                                    ? 'bg-atomic-tangerine-400 hover:bg-atomic-tangerine-500 text-white'
                                    : 'bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                            "
                        >
                            {{ tag.name }}
                        </button>
                    </div>
                    <button
                        v-if="availableTags.length > 5"
                        @click="showAllTags = !showAllTags"
                        class="text-atomic-tangerine-400 hover:text-atomic-tangerine-500 self-start text-sm"
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
                            @click="handleTechnologyToggle(tech.id)"
                            class="flex items-center gap-2 rounded-full px-3 py-1 text-sm transition-colors"
                            :class="
                                selectedTechnologies.includes(tech.id)
                                    ? 'bg-atomic-tangerine-400 hover:bg-atomic-tangerine-500 text-white'
                                    : 'bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                            "
                        >
                            <img v-if="tech.iconPicture" :src="tech.iconPicture.webp.thumbnail" :alt="tech.name" class="h-4 w-4 object-contain" />
                            {{ tech.name }}
                        </button>
                    </div>
                    <button
                        v-if="availableTechnologies.length > 5"
                        @click="showAllTechnologies = !showAllTechnologies"
                        class="text-atomic-tangerine-400 hover:text-atomic-tangerine-500 self-start text-sm"
                    >
                        {{ showAllTechnologies ? t('search.show_less') : t('search.show_more') }}
                    </button>
                </div>

                <!-- Clear Filters -->
                <button v-if="hasActiveFilters" @click="handleClearFilters" class="self-start text-sm text-red-500 hover:text-red-600">
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
