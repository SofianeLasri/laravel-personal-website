<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
}>();

defineEmits<{
    navigate: [page: number];
}>();

const startItem = computed(() => {
    return (props.currentPage - 1) * props.perPage + 1;
});

const endItem = computed(() => {
    return Math.min(props.currentPage * props.perPage, props.total);
});

const visiblePages = computed(() => {
    const pages: (number | string)[] = [];
    const delta = 2; // Number of pages to show on each side of current page

    // Always show first page
    if (props.lastPage > 1) {
        pages.push(1);
    }

    // Calculate range around current page
    const start = Math.max(2, props.currentPage - delta);
    const end = Math.min(props.lastPage - 1, props.currentPage + delta);

    // Add ellipsis after first page if needed
    if (start > 2) {
        pages.push('...');
    }

    // Add pages around current page
    for (let i = start; i <= end; i++) {
        pages.push(i);
    }

    // Add ellipsis before last page if needed
    if (end < props.lastPage - 1) {
        pages.push('...');
    }

    // Always show last page
    if (props.lastPage > 1) {
        pages.push(props.lastPage);
    }

    return pages;
});
</script>

<template>
    <div class="flex items-center justify-between">
        <div class="text-muted-foreground text-sm">Affichage de {{ startItem }} à {{ endItem }} sur {{ total }} résultats</div>

        <div class="flex items-center space-x-2">
            <Button variant="outline" size="sm" :disabled="currentPage <= 1" @click="$emit('navigate', currentPage - 1)">
                <ChevronLeftIcon class="h-4 w-4" />
                Précédent
            </Button>

            <div class="flex items-center space-x-1">
                <template v-for="page in visiblePages" :key="page">
                    <Button
                        v-if="page !== '...'"
                        variant="outline"
                        size="sm"
                        :class="{ 'bg-primary text-primary-foreground': page === currentPage }"
                        @click="$emit('navigate', page)"
                    >
                        {{ page }}
                    </Button>
                    <span v-else class="text-muted-foreground px-2">...</span>
                </template>
            </div>

            <Button variant="outline" size="sm" :disabled="currentPage >= lastPage" @click="$emit('navigate', currentPage + 1)">
                Suivant
                <ChevronRightIcon class="h-4 w-4" />
            </Button>
        </div>
    </div>
</template>
