<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    currentPage: number;
    lastPage: number;
    total: number;
    from: number | null;
    to: number | null;
}

const props = defineProps<Props>();

const pages = computed(() => {
    const pages = [];
    const maxVisible = 5;
    const halfVisible = Math.floor(maxVisible / 2);

    let startPage = Math.max(1, props.currentPage - halfVisible);
    let endPage = Math.min(props.lastPage, props.currentPage + halfVisible);

    // Adjust if we're near the beginning
    if (props.currentPage <= halfVisible) {
        endPage = Math.min(maxVisible, props.lastPage);
    }

    // Adjust if we're near the end
    if (props.currentPage > props.lastPage - halfVisible) {
        startPage = Math.max(1, props.lastPage - maxVisible + 1);
    }

    for (let i = startPage; i <= endPage; i++) {
        pages.push(i);
    }

    return pages;
});

const goToPage = (page: number) => {
    if (page < 1 || page > props.lastPage || page === props.currentPage) {
        return;
    }

    const url = new URL(window.location.href);
    url.searchParams.set('page', page.toString());

    router.get(
        url.pathname + url.search,
        {},
        {
            preserveState: true,
            preserveScroll: false,
        },
    );
};
</script>

<template>
    <div v-if="lastPage > 1" class="flex flex-col items-center gap-4">
        <!-- Pagination Controls -->
        <div class="flex items-center gap-2">
            <!-- First Page -->
            <Button
                variant="outline"
                size="icon"
                :disabled="currentPage === 1"
                :class="currentPage === 1 ? 'cursor-not-allowed' : 'cursor-pointer'"
                @click="goToPage(1)"
            >
                <ChevronsLeft class="h-4 w-4" />
            </Button>

            <!-- Previous Page -->
            <Button
                variant="outline"
                size="icon"
                :disabled="currentPage === 1"
                :class="currentPage === 1 ? 'cursor-not-allowed' : 'cursor-pointer'"
                @click="goToPage(currentPage - 1)"
            >
                <ChevronLeft class="h-4 w-4" />
            </Button>

            <!-- Page Numbers -->
            <div class="flex gap-1">
                <Button v-if="pages[0] > 1" variant="ghost" size="icon" disabled class="cursor-not-allowed"> ... </Button>

                <Button
                    v-for="page in pages"
                    :key="page"
                    :variant="page === currentPage ? 'default' : 'outline'"
                    size="icon"
                    class="cursor-pointer"
                    @click="goToPage(page)"
                >
                    {{ page }}
                </Button>

                <Button v-if="pages[pages.length - 1] < lastPage" variant="ghost" size="icon" disabled class="cursor-not-allowed"> ... </Button>
            </div>

            <!-- Next Page -->
            <Button
                variant="outline"
                size="icon"
                :disabled="currentPage === lastPage"
                :class="currentPage === lastPage ? 'cursor-not-allowed' : 'cursor-pointer'"
                @click="goToPage(currentPage + 1)"
            >
                <ChevronRight class="h-4 w-4" />
            </Button>

            <!-- Last Page -->
            <Button
                variant="outline"
                size="icon"
                :disabled="currentPage === lastPage"
                :class="currentPage === lastPage ? 'cursor-not-allowed' : 'cursor-pointer'"
                @click="goToPage(lastPage)"
            >
                <ChevronsRight class="h-4 w-4" />
            </Button>
        </div>

        <!-- Results Info -->
        <div class="text-design-system-paragraph text-sm">
            <span v-if="from && to"> Affichage de {{ from }} à {{ to }} sur {{ total }} articles </span>
            <span v-else> {{ total }} articles au total </span>
        </div>
    </div>
</template>
