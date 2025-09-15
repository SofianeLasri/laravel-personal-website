<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';

const lightElement = ref<HTMLElement>();
let animationFrameId: number | null = null;

const isDesktopOrTablet = () => {
    return window.innerWidth >= 768; // Tailwind's md breakpoint
};

const animate = () => {
    if (!lightElement.value || !isDesktopOrTablet()) return;

    // Generate smooth random movement using sine waves
    const time = Date.now() * 0.00015; // Slightly faster time factor

    // Multiple sine waves for organic movement with increased amplitude
    const xOffset =
        Math.sin(time * 1.2) * 64 + // Primary movement (was 15)
        Math.sin(time * 2.7) * 32 + // Secondary movement (was 8)
        Math.sin(time * 4.3) * 16; // Subtle detail (was 5)

    const yOffset =
        Math.cos(time * 1.5) * 42 + // Primary movement (was 15)
        Math.cos(time * 3.2) * 24 + // Secondary movement (was 8)
        Math.cos(time * 5.1) * 16; // Subtle detail (was 5)

    // Apply transform
    lightElement.value.style.transform = `translate(${xOffset}px, ${yOffset}px)`;

    animationFrameId = requestAnimationFrame(animate);
};

onMounted(() => {
    if (isDesktopOrTablet()) {
        animate();
    }

    // Listen for resize to enable/disable animation
    const handleResize = () => {
        if (isDesktopOrTablet() && !animationFrameId) {
            animate();
        } else if (!isDesktopOrTablet() && animationFrameId) {
            cancelAnimationFrame(animationFrameId);
            animationFrameId = null;
            if (lightElement.value) {
                lightElement.value.style.transform = '';
            }
        }
    };

    window.addEventListener('resize', handleResize);

    // Cleanup on unmount
    onUnmounted(() => {
        if (animationFrameId) {
            cancelAnimationFrame(animationFrameId);
        }
        window.removeEventListener('resize', handleResize);
    });
});
</script>

<template>
    <div
        ref="lightElement"
        class="light-shape from-atomic-tangerine-400/40 to-atomic-tangerine-400/0 size-[32rem] rounded-full bg-radial-[50%_50%_at_50%_50%]"
    ></div>
</template>

<style scoped>
.light-shape {
    will-change: transform;
    transition: opacity 0.3s ease;
}

@media (min-width: 768px) {
    .light-shape {
        animation: subtle-glow 4s ease-in-out infinite alternate;
    }
}

@keyframes subtle-glow {
    0% {
        opacity: 1;
    }
    100% {
        opacity: 0.85;
    }
}
</style>
