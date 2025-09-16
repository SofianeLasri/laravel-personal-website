<script setup lang="ts">
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ref, watch } from 'vue';

interface Props {
    currentSort?: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'sort-change', value: string): void;
}>();

const selectedSort = ref(props.currentSort || 'newest');

const sortOptions = [
    { value: 'newest', label: 'Plus récent' },
    { value: 'oldest', label: 'Plus ancien' },
    { value: 'alphabetical', label: 'Alphabétique' },
];

watch(
    () => props.currentSort,
    (newSort) => {
        if (newSort) {
            selectedSort.value = newSort;
        }
    },
);

watch(selectedSort, (newSort) => {
    emit('sort-change', newSort);
});
</script>

<template>
    <div class="flex w-full flex-col items-start overflow-hidden rounded-2xl border bg-gray-100 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex w-full items-center justify-between gap-2.5 px-4 py-3">
            <div class="text-design-system-title justify-center">Trier par</div>
        </div>

        <div class="w-full bg-white p-2 dark:bg-gray-950">
            <Select v-model="selectedSort">
                <SelectTrigger class="w-full border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-950">
                    <SelectValue placeholder="Trier par" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="option in sortOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>
    </div>
</template>