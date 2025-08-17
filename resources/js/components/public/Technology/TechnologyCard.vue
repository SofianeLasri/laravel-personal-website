<script setup lang="ts">
import { SSRPicture } from '@/types';

defineProps<{
    name: string;
    description: string;
    iconPicture?: SSRPicture;
}>();
</script>

<template>
    <div
        class="flex items-center justify-center gap-2 rounded-lg p-2 outline-1 outline-gray-200 transition-all hover:scale-[1.01] dark:border dark:border-gray-700/30 dark:bg-gray-900/50 dark:outline-gray-700 dark:hover:bg-gray-900/70"
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
    </div>
</template>
