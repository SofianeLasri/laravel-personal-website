<script setup lang="ts">
import BaseButton from '@/components/public/Ui/Button/BaseButton.vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

interface BlogTypeOption {
    value: string;
    label: string;
    postCount?: number;
}

const props = defineProps<{
    name: string;
    availableTypes: Record<string, string>;
    typePostCounts?: Record<string, number>;
    initialSelectedFilters?: string[];
}>();

const emit = defineEmits<{
    (e: 'filter-change', value: string[]): void;
}>();

const selectedFilters = ref<Set<string>>(new Set(props.initialSelectedFilters || []));

// Check if screen is mobile size (< lg breakpoint = 1024px)
const isMobile = ref(typeof window !== 'undefined' ? window.innerWidth < 1024 : false);
const isCollapsed = ref(isMobile.value);

const sortedTypes = computed(() => {
    const types: BlogTypeOption[] = Object.entries(props.availableTypes).map(([value, label]) => ({
        value,
        label,
        postCount: props.typePostCounts?.[value] || 0,
    }));

    return types.filter((type) => type.postCount > 0).sort((a, b) => (b.postCount || 0) - (a.postCount || 0));
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

const toggleFilter = (typeValue: string) => {
    if (selectedFilters.value.has(typeValue)) {
        selectedFilters.value.delete(typeValue);
    } else {
        selectedFilters.value.add(typeValue);
    }

    emit('filter-change', Array.from(selectedFilters.value));
};
</script>

<template>
    <div
        class="flex max-h-96 w-full flex-col items-start overflow-hidden rounded-2xl border bg-gray-100 dark:border-gray-800 dark:bg-gray-900"
        data-testid="blog-type-filter"
    >
        <div
            class="flex w-full cursor-pointer items-center justify-between gap-2.5 px-4 py-3 transition-colors hover:bg-gray-200 dark:hover:bg-gray-800"
            :aria-expanded="!isCollapsed"
            @click="toggleCollapse"
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
            class="bordertransition-all flex flex-col self-stretch overflow-hidden rounded-2xl rounded-b-none duration-300 ease-in-out"
            :class="isCollapsed ? 'max-h-0 opacity-0' : 'max-h-96 opacity-100'"
        >
            <div class="custom-scrollbar flex flex-col items-start gap-2 overflow-y-auto bg-white p-2 dark:bg-gray-950">
                <template v-for="type in sortedTypes" :key="type.value">
                    <BaseButton
                        v-if="selectedFilters.has(type.value)"
                        variant="active"
                        class="w-full rounded-lg !px-3 py-2"
                        :data-filter-type="props.name.toLowerCase()"
                        :data-filter-value="type.value"
                        data-selected="true"
                        @click="toggleFilter(type.value)"
                    >
                        <div class="flex grow items-center gap-2">
                            <div class="flex grow items-center gap-2">
                                <div>{{ type.label }}</div>
                            </div>
                        </div>
                        <div class="text-design-system-paragraph shrink-0">{{ type.postCount || 0 }}</div>
                    </BaseButton>

                    <BaseButton
                        v-else
                        variant="white"
                        class="w-full rounded-lg border-none !px-3 py-2"
                        :data-filter-type="props.name.toLowerCase()"
                        :data-filter-value="type.value"
                        data-selected="false"
                        @click="toggleFilter(type.value)"
                    >
                        <div class="flex grow items-center gap-2">
                            <div class="flex grow items-center gap-2">
                                <div>{{ type.label }}</div>
                            </div>
                        </div>
                        <div class="text-design-system-paragraph shrink-0">{{ type.postCount || 0 }}</div>
                    </BaseButton>
                </template>

                <div v-if="sortedTypes.length === 0" class="w-full px-3 py-2 text-center text-gray-500 dark:text-gray-400">Aucun type disponible</div>
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