<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    svg: string;
    class?: string;
}>();

const svgContent = computed(() => {
    // Parse SVG and add necessary attributes for Safari compatibility
    const parser = new DOMParser();
    const doc = parser.parseFromString(props.svg, 'image/svg+xml');
    const svgElement = doc.querySelector('svg');

    if (svgElement) {
        // Ensure SVG has proper namespace
        if (!svgElement.getAttribute('xmlns')) {
            svgElement.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        }

        // Add width and height if not present
        if (!svgElement.getAttribute('width') && !svgElement.getAttribute('height')) {
            svgElement.setAttribute('width', '100%');
            svgElement.setAttribute('height', '100%');
        }

        return svgElement.outerHTML;
    }

    return props.svg;
});
</script>

<template>
    <span :class="props.class" v-html="svgContent"></span>
</template>