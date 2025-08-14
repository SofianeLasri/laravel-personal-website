<script setup lang="ts">
import ActiveButton from '@/components/public/Ui/Button/ActiveButton.vue';
import WhiteButton from '@/components/public/Ui/Button/WhiteButton.vue';
import { useTranslation } from '@/composables/useTranslation';
import { SSRTechnology } from '@/types';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    name: string;
    technologies: SSRTechnology[];
    initialSelectedFilters?: number[];
}>();

const { t } = useTranslation();

const emit = defineEmits<{
    (e: 'filter-change', value: number[]): void;
}>();

const selectedFilters = ref<Set<number>>(new Set(props.initialSelectedFilters || []));

// Check if screen is mobile size (< lg breakpoint = 1024px)
const isMobile = ref(typeof window !== 'undefined' ? window.innerWidth < 1024 : false);
const isCollapsed = ref(isMobile.value);

const sortedTechnologies = computed(() => {
    return [...props.technologies].sort((a, b) => b.creationCount - a.creationCount);
});

const handleResize = () => {
    if (typeof window === 'undefined') return;

    const wasMobile = isMobile.value;
    isMobile.value = window.innerWidth < 1024;

    // If switching from mobile to desktop, expand the filter
    if (wasMobile && !isMobile.value) {
        isCollapsed.value = false;
    }
    // If switching from desktop to mobile, collapse the filter
    else if (!wasMobile && isMobile.value) {
        isCollapsed.value = true;
    }
};

onMounted(() => {
    if (typeof window !== 'undefined') {
        window.addEventListener('resize', handleResize);
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', handleResize);
    }
});

watch(
    () => props.initialSelectedFilters,
    (newFilters) => {
        if (newFilters) {
            selectedFilters.value = new Set(newFilters);
        } else {
            selectedFilters.value.clear();
        }
    },
    { deep: true },
);

const toggleCollapse = () => {
    isCollapsed.value = !isCollapsed.value;
};

const toggleFilter = (techId: number) => {
    if (selectedFilters.value.has(techId)) {
        selectedFilters.value.delete(techId);
    } else {
        selectedFilters.value.add(techId);
    }

    emit('filter-change', Array.from(selectedFilters.value));
};
</script>

<template>
    <div class="flex max-h-96 w-full flex-col items-start overflow-hidden rounded-2xl border bg-gray-100 dark:border-gray-800 dark:bg-gray-900">
        <div
            class="flex w-full cursor-pointer items-center justify-between gap-2.5 px-4 py-3 transition-colors hover:bg-gray-200 dark:hover:bg-gray-800"
            @click="toggleCollapse"
            :aria-expanded="!isCollapsed"
        >
            <div class="text-design-system-title justify-center">{{ name }}</div>
            <svg
                class="h-5 w-5 transition-transform duration-300 dark:text-gray-200"
                :class="isCollapsed ? '' : 'rotate-180'"
                viewBox="0 0 24 24"
                stroke="currentColor"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" fill="none" />
            </svg>
        </div>

        <div
            class="outline-border flex flex-col self-stretch overflow-hidden rounded-2xl rounded-b-none outline-1 transition-all duration-300 ease-in-out"
            :class="isCollapsed ? 'max-h-0 opacity-0' : 'max-h-96 opacity-100'"
        >
            <div class="custom-scrollbar flex flex-col items-start gap-2 overflow-y-auto bg-white p-2 dark:bg-gray-950">
                <template v-for="tech in sortedTechnologies" :key="tech.id">
                    <ActiveButton v-if="selectedFilters.has(tech.id)" class="w-full rounded-lg !px-3 py-2" @click="toggleFilter(tech.id)">
                        <div class="flex grow items-center gap-2">
                            <div
                                class="flex aspect-square size-8 items-center justify-center rounded-lg border bg-white p-2 dark:border-gray-700 dark:bg-gray-900"
                            >
                                <img
                                    :src="tech.iconPicture.webp.thumbnail"
                                    :alt="`${tech.name} icon`"
                                    class="size-full object-contain"
                                    loading="lazy"
                                />
                            </div>
                            <div>{{ tech.name }}</div>
                        </div>
                        <div class="text-design-system-paragraph shrink-0">{{ tech.creationCount }}</div>
                    </ActiveButton>

                    <WhiteButton v-else class="w-full rounded-lg border-none !px-3 py-2" @click="toggleFilter(tech.id)">
                        <div class="flex grow items-center gap-2">
                            <div
                                class="flex aspect-square size-8 items-center justify-center rounded-lg border bg-white p-2 dark:border-gray-700 dark:bg-gray-900"
                            >
                                <img
                                    :src="tech.iconPicture.webp.thumbnail"
                                    :alt="`${tech.name} icon`"
                                    class="size-full object-contain"
                                    loading="lazy"
                                />
                            </div>
                            <div>{{ tech.name }}</div>
                        </div>
                        <div class="text-design-system-paragraph shrink-0">{{ tech.creationCount }}</div>
                    </WhiteButton>
                </template>

                <div v-if="sortedTechnologies.length === 0" class="w-full px-3 py-2 text-center text-gray-500 dark:text-gray-400">
                    {{ t('projects.no_technology_available') }}
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar {
    /* Firefox */
    scrollbar-width: thin;
    scrollbar-color: var(--gray-200) transparent;

    /* Webkit */
    &::-webkit-scrollbar {
        width: 6px;
    }

    &::-webkit-scrollbar-track {
        background: transparent;
        border-radius: 10px;
    }

    &::-webkit-scrollbar-thumb {
        background-color: var(--gray-200);
        border-radius: 10px;
        border: 2px solid transparent;
    }

    &::-webkit-scrollbar-thumb:hover {
        background-color: var(--gray-400);
    }
}

/* Dark mode scrollbar styles */
:global(.dark) .custom-scrollbar {
    scrollbar-color: var(--gray-700) transparent;
}

:global(.dark) .custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: var(--gray-700);
}

:global(.dark) .custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background-color: var(--gray-600);
}
</style>
