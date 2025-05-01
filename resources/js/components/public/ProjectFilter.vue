<script setup lang="ts">
import ActiveButton from '@/components/public/Ui/Button/ActiveButton.vue';
import WhiteButton from '@/components/public/Ui/Button/WhiteButton.vue';
import { ref } from 'vue';
import { SSRTechnology } from '@/types';

defineProps<{
    name: string;
    technologies: SSRTechnology[];
}>();

const emit = defineEmits<{
    (e: 'filter-change', value: number | null): void;
}>();

const selectedFilter = ref<number | null>(null);

const toggleFilter = (techId: number) => {
    if (selectedFilter.value === techId) {
        selectedFilter.value = null;
    } else {
        selectedFilter.value = techId;
    }

    emit('filter-change', selectedFilter.value);
};
</script>

<template>
    <div class="flex max-h-96 w-full flex-col items-start rounded-2xl border bg-gray-100">
        <div class="flex items-center gap-2.5 px-4 py-3">
            <div class="text-design-system-title justify-center">{{ name }}</div>
        </div>
        <div class="custom-scrollbar flex flex-col items-start gap-2 self-stretch overflow-y-auto rounded-2xl border bg-white p-2">
            <template v-for="tech in technologies" :key="tech.id">
                <ActiveButton v-if="selectedFilter === tech.id" class="w-full rounded-lg !px-3 py-2" @click="toggleFilter(tech.id)">
                    <div class="flex grow items-center gap-2">
                        <div class="flex aspect-square size-8 items-center justify-center rounded-lg border bg-white p-2">
                            <div v-html="tech.svgIcon"></div>
                        </div>
                        <div>{{ tech.name }}</div>
                    </div>
                    <div class="text-design-system-paragraph shrink-0">{{ tech.creationCount }}</div>
                </ActiveButton>

                <WhiteButton v-else class="w-full rounded-lg border-none !px-3 py-2" @click="toggleFilter(tech.id)">
                    <div class="flex grow items-center gap-2">
                        <div class="flex aspect-square size-8 items-center justify-center rounded-lg border bg-white p-2">
                            <div v-html="tech.svgIcon"></div>
                        </div>
                        <div>{{ tech.name }}</div>
                    </div>
                    <div class="text-design-system-paragraph shrink-0">{{ tech.creationCount }}</div>
                </WhiteButton>
            </template>

            <div v-if="technologies.length === 0" class="w-full px-3 py-2 text-center text-gray-500">Aucune technologie disponible</div>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar {
    /* Pour Firefox */
    scrollbar-width: thin;
    scrollbar-color: var(--color-gray-200) transparent;

    /* Pour les autres navigateurs */
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
