<script setup lang="ts">
import { useTranslation } from '@/composables/useTranslation';
import { SSRSimplifiedCreation } from '@/types';
import { router } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import { useRoute } from '@/composables/useRoute';

defineProps<{
    results: SSRSimplifiedCreation[];
    loading?: boolean;
    hasQuery?: boolean;
}>();

const emit = defineEmits<{
    select: [project: SSRSimplifiedCreation];
}>();

const { t } = useTranslation();
const route = useRoute();

const handleProjectClick = (project: SSRSimplifiedCreation) => {
    emit('select', project);
    router.visit(route('public.projects.show', { slug: project.slug }));
};

const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', { year: 'numeric', month: 'long' });
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-8">
            <div class="border-t-atomic-tangerine-400 h-8 w-8 animate-spin rounded-full border-4 border-gray-300"></div>
        </div>

        <!-- No Results -->
        <div v-else-if="hasQuery && results.length === 0" class="flex flex-col items-center justify-center py-8 text-gray-500">
            <p class="text-lg">{{ t('search.no_results') }}</p>
            <p class="text-sm">{{ t('search.try_different_keywords') }}</p>
        </div>

        <!-- Results List -->
        <div v-else-if="results.length > 0" class="flex flex-col gap-2">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ t('search.found_results', { count: results.length }) }}
            </p>

            <div class="custom-scrollbar max-h-96 overflow-y-auto">
                <button
                    v-for="project in results"
                    :key="project.id"
                    @click="handleProjectClick(project)"
                    class="group flex w-full items-start gap-4 rounded-lg p-4 text-left transition-colors hover:bg-gray-100 dark:hover:bg-gray-800"
                >
                    <!-- Project Image -->
                    <div v-if="project.mainPicture" class="h-16 w-16 flex-shrink-0 overflow-hidden rounded-lg">
                        <img :src="project.mainPicture.webp.thumbnail" :alt="project.title" class="h-full w-full object-cover" loading="lazy" />
                    </div>
                    <div v-else class="h-16 w-16 flex-shrink-0 rounded-lg bg-gray-200 dark:bg-gray-700"></div>

                    <!-- Project Info -->
                    <div class="flex flex-1 flex-col gap-1">
                        <h3 class="group-hover:text-atomic-tangerine-400 font-medium text-gray-900 dark:text-gray-100">
                            {{ project.title }}
                        </h3>
                        <p v-if="project.shortDescription" class="line-clamp-2 text-sm text-gray-600 dark:text-gray-400">
                            {{ project.shortDescription }}
                        </p>
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-500">
                            <span v-if="project.createdAt">{{ formatDate(project.createdAt) }}</span>
                            <span v-if="project.tags && project.tags.length > 0" class="flex gap-1">
                                <span v-for="(tag, index) in project.tags.slice(0, 3)" :key="tag.id">
                                    {{ tag.name }}<span v-if="index < Math.min(2, project.tags.length - 1)">,</span>
                                </span>
                                <span v-if="project.tags.length > 3">+{{ project.tags.length - 3 }}</span>
                            </span>
                        </div>
                    </div>

                    <!-- Arrow Icon -->
                    <ArrowRight
                        class="group-hover:text-atomic-tangerine-400 h-5 w-5 flex-shrink-0 text-gray-400 transition-transform group-hover:translate-x-1"
                    />
                </button>
            </div>
        </div>

        <!-- Empty State (no search performed yet) -->
        <div v-else class="flex flex-col items-center justify-center py-8 text-gray-500">
            <p class="text-sm">{{ t('search.start_typing') }}</p>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: rgba(156, 163, 175, 0.5);
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background-color: rgba(156, 163, 175, 0.7);
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>