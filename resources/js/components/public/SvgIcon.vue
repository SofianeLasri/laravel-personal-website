<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    svg: string;
    class?: string;
}>();

const svgContent = computed(() => {
    // For SSR compatibility, we'll use regex instead of DOMParser
    let svgString = props.svg;

    // Ensure SVG has proper namespace
    if (!svgString.includes('xmlns=')) {
        svgString = svgString.replace('<svg', '<svg xmlns="http://www.w3.org/2000/svg"');
    }

    // Add width and height if not present
    if (!svgString.includes('width=') && !svgString.includes('height=')) {
        svgString = svgString.replace('<svg', '<svg width="100%" height="100%"');
    }

    return svgString;
});
</script>

<template>
    <span :class="props.class" v-html="svgContent"></span>
</template>
