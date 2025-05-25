<script setup lang="ts">
import ActiveButton from '@/components/public/Ui/Button/ActiveButton.vue';
import WhiteButton from '@/components/public/Ui/Button/WhiteButton.vue';
import { useTranslation } from '@/composables/useTranslation';
import { SSRTechnology } from '@/types';
import { ref, watch } from 'vue';

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
const isCollapsed = ref(false);

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
    <div class="flex max-h-96 w-full flex-col items-start overflow-hidden rounded-2xl border bg-gray-100">
        <div class="flex w-full cursor-pointer items-center justify-between gap-2.5 px-4 py-3" @click="toggleCollapse" :aria-expanded="!isCollapsed">
            <div class="text-design-system-title justify-center">{{ name }}</div>
            <svg
                class="h-5 w-5 transition-transform duration-300"
                :class="isCollapsed ? '' : 'rotate-180'"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </div>

        <div
            class="outline-border flex flex-col self-stretch overflow-hidden rounded-2xl rounded-b-none outline-1 transition-all duration-300 ease-in-out"
            :class="isCollapsed ? 'max-h-0 opacity-0' : 'max-h-96 opacity-100'"
        >
            <div class="custom-scrollbar flex flex-col items-start gap-2 overflow-y-auto bg-white p-2">
                <template v-for="tech in technologies" :key="tech.id">
                    <ActiveButton v-if="selectedFilters.has(tech.id)" class="w-full rounded-lg !px-3 py-2" @click="toggleFilter(tech.id)">
                        <div class="flex grow items-center gap-2">
                            <div class="flex aspect-square size-8 items-center justify-center rounded-lg border bg-white p-2">
                                <div v-html="tech.svgIcon" class="size-full"></div>
                            </div>
                            <div>{{ tech.name }}</div>
                        </div>
                        <div class="text-design-system-paragraph shrink-0">{{ tech.creationCount }}</div>
                    </ActiveButton>

                    <WhiteButton v-else class="w-full rounded-lg border-none !px-3 py-2" @click="toggleFilter(tech.id)">
                        <div class="flex grow items-center gap-2">
                            <div class="flex aspect-square size-8 items-center justify-center rounded-lg border bg-white p-2">
                                <div v-html="tech.svgIcon" class="size-full"></div>
                            </div>
                            <div>{{ tech.name }}</div>
                        </div>
                        <div class="text-design-system-paragraph shrink-0">{{ tech.creationCount }}</div>
                    </WhiteButton>
                </template>

                <div v-if="technologies.length === 0" class="w-full px-3 py-2 text-center text-gray-500">
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
    scrollbar-color: var(--color-gray-200) transparent;

    /* Webkit */
    &::-webkit-scrollbar {
        width: 6px;
    }

    &::-webkit-scrollbar-track {
        background: transparent;
        border-radius: 10px;
    }

    &::-webkit-scrollbar-thumb {
        background-color: var(--color-gray-200);
        border-radius: 10px;
        border: 2px solid transparent;
    }

    &::-webkit-scrollbar-thumb:hover {
        background-color: var(--color-gray-400);
    }
}
</style>
