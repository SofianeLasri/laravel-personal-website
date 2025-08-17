<script setup lang="ts">
import { SSRPicture } from '@/types';

defineProps<{
    name: string;
    description: string;
    iconPicture?: SSRPicture;
}>();
</script>

<template>
    <div class="flex items-center justify-center gap-2 rounded-lg p-2 outline-1 outline-gray-200 dark:outline-gray-700">
        <div class="flex size-10 items-center justify-center lg:size-16" v-if="iconPicture">
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
