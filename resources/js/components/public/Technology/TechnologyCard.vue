<script setup lang="ts">
import { SSRPicture, SSRTechnology } from '@/types';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    name: string;
    description: string;
    iconPicture?: SSRPicture;
    technology?: SSRTechnology;
}>();

const projectsUrl = computed(() => {
    if (!props.technology) return null;

    const params = new URLSearchParams();
    params.set('tab', 'development');

    // Détermine le type de filtre selon le type de technologie
    if (props.technology.type === 'framework') {
        params.set('frameworks', props.technology.id.toString());
    } else if (props.technology.type === 'library') {
        params.set('libraries', props.technology.id.toString());
    } else if (props.technology.type === 'game_engine') {
        params.set('gameEngines', props.technology.id.toString());
    } else if (props.technology.type === 'language') {
        params.set('languages', props.technology.id.toString());
    }

    return `/projects?${params.toString()}`;
});
</script>

<template>
    <component
        :is="technology && projectsUrl ? Link : 'div'"
        :href="projectsUrl"
        class="flex items-center justify-center gap-2 rounded-lg p-2 outline-1 outline-gray-200 transition-all hover:scale-[1.01] dark:border dark:border-gray-700/30 dark:bg-gray-900/50 dark:outline-gray-700 dark:hover:bg-gray-900/70"
        :class="{ 'cursor-pointer': technology && projectsUrl }"
    >
        <div class="flex size-10 items-center justify-center rounded-lg bg-white p-1.5 lg:size-16 dark:bg-white" v-if="iconPicture">
            <img :src="iconPicture.webp.small" :alt="`${name} icon`" class="h-full w-full object-contain" loading="lazy" />
        </div>
        <div class="flex w-full flex-col justify-center gap-1">
            <div class="text-design-system-title text-sm font-bold lg:text-base">{{ name }}</div>
            <div class="text-design-system-paragraph w-full justify-center text-xs lg:text-sm">
                {{ description }}
            </div>
        </div>
    </component>
</template>
