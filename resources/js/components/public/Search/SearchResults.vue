<script setup lang="ts">
import MagnifyingGlassRegular from '@/components/font-awesome/MagnifyingGlassRegular.vue';
import { useRoute } from '@/composables/useRoute';
import { useTranslation } from '@/composables/useTranslation';
import { SSRSimplifiedCreation } from '@/types';
import { router } from '@inertiajs/vue3';

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
</script>

<template>
    <div>
        <!-- Loading state -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <div class="text-design-system-paragraph">{{ t('search.searching') }}</div>
        </div>

        <!-- No query state -->
        <div v-else-if="!hasQuery" class="flex flex-col items-center justify-center py-12 text-center">
            <MagnifyingGlassRegular class="mb-4 h-12 w-12 fill-gray-400" />
            <h3 class="text-design-system-title mb-2 text-lg font-semibold">{{ t('search.start_search') }}</h3>
            <p class="text-design-system-paragraph">{{ t('search.start_search_description') }}</p>
        </div>

        <!-- No results -->
        <div v-else-if="results.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
            <div class="text-design-system-paragraph mb-2 text-lg font-semibold">{{ t('search.no_results') }}</div>
            <p class="text-design-system-paragraph">{{ t('search.no_results_description') }}</p>
        </div>

        <!-- Results -->
        <div v-else>
            <div class="text-design-system-paragraph mb-4 text-sm">
                {{ results.length }} {{ results.length > 1 ? t('search.results') : t('search.result') }}
                {{ results.length > 1 ? t('search.found_plural') : t('search.found_singular') }}
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <button
                    v-for="project in results"
                    :key="project.id"
                    @click="handleProjectClick(project)"
                    class="group dark:hover:bg-gray-750 flex cursor-pointer items-start gap-4 rounded-lg border bg-white p-4 text-left transition-all hover:bg-gray-50 hover:shadow-md dark:border-gray-700 dark:bg-gray-800"
                >
                    <!-- Project logo -->
                    <div
                        class="flex h-16 w-16 flex-shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-gray-50 dark:border-gray-700 dark:bg-gray-800"
                    >
                        <picture v-if="project.logo" class="h-full w-full">
                            <source :srcset="project.logo.webp.small" type="image/webp" />
                            <img
                                :src="project.logo.avif.small"
                                :alt="t('search.project_logo_alt', { name: project.name })"
                                class="h-full w-full object-cover"
                                loading="lazy"
                            />
                        </picture>
                    </div>

                    <!-- Project info -->
                    <div class="min-w-0 flex-1">
                        <h3 class="text-design-system-title group-hover:text-primary mb-1 font-semibold">
                            {{ project.name }}
                        </h3>
                        <p class="text-design-system-paragraph mb-2 text-sm">{{ project.type }}</p>
                        <p class="text-design-system-paragraph line-clamp-2 text-sm">
                            {{ project.shortDescription }}
                        </p>

                        <!-- Technologies -->
                        <div v-if="project.technologies && project.technologies.length > 0" class="mt-3 flex flex-wrap gap-1">
                            <span
                                v-for="tech in project.technologies.slice(0, 3)"
                                :key="tech.id"
                                class="inline-flex items-center gap-1 rounded bg-gray-100 px-2 py-1 text-xs dark:bg-gray-700"
                            >
                                <img
                                    v-if="tech.iconPicture"
                                    :src="tech.iconPicture.webp.thumbnail"
                                    :alt="`${tech.name} icon`"
                                    class="h-3 w-3 object-contain"
                                    loading="lazy"
                                />
                                {{ tech.name }}
                            </span>
                            <span
                                v-if="project.technologies.length > 3"
                                class="inline-flex items-center rounded bg-gray-100 px-2 py-1 text-xs dark:bg-gray-700"
                            >
                                +{{ project.technologies.length - 3 }}
                            </span>
                        </div>
                    </div>
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
