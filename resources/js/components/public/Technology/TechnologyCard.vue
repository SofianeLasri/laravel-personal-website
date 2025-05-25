<script setup lang="ts">
defineProps<{
    name: string;
    description: string;
    svgIcon?: string;
}>();
</script>

<template>
    <div class="flex items-center justify-center gap-2 rounded-lg p-2 outline-1 outline-gray-200">
        <div class="flex size-10 items-center justify-center lg:size-16" v-html="svgIcon" v-if="svgIcon"></div>
        <div class="flex w-full flex-col justify-center gap-1">
            <div class="text-design-system-title text-sm lg:text-base">{{ name }}</div>
            <div class="text-design-system-paragraph w-full justify-center text-xs lg:text-sm">
                {{ description }}
            </div>
        </div>
    </div>
</template>
