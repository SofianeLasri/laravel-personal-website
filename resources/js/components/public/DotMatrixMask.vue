<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useDotMatrixStore } from '@/stores/dotMatrix';

const dotMatrixStore = useDotMatrixStore();

const mouseX = ref(-1000);
const mouseY = ref(-1000);
const isHovering = ref(false);

// Check if device is mobile
const isMobile = computed(() => {
    if (typeof window === 'undefined') return false;
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || window.innerWidth < 768;
});

// Should show effect
const showEffect = computed(() => {
    return dotMatrixStore.isEnabled && !isMobile.value && isHovering.value;
});

// CSS variables for the radial gradient mask
const maskStyle = computed(() => {
    if (!showEffect.value) {
        return {
            '--mouse-x': '-1000px',
            '--mouse-y': '-1000px',
            opacity: 0,
        };
    }

    return {
        '--mouse-x': `${mouseX.value}px`,
        '--mouse-y': `${mouseY.value}px`,
        opacity: 1,
    };
});

const handleMouseMove = (e: MouseEvent) => {
    if (!dotMatrixStore.isEnabled || isMobile.value) return;

    // Use clientX/clientY since container is now fixed
    mouseX.value = e.clientX;
    mouseY.value = e.clientY;
    isHovering.value = true;
};

const handleMouseLeave = () => {
    isHovering.value = false;
};

onMounted(() => {
    if (!isMobile.value) {
        window.addEventListener('mousemove', handleMouseMove);
        window.addEventListener('mouseleave', handleMouseLeave);
    }
});

onUnmounted(() => {
    window.removeEventListener('mousemove', handleMouseMove);
    window.removeEventListener('mouseleave', handleMouseLeave);
});
</script>

<template>
    <div class="dot-matrix-container">
        <!-- Orange dots layer (bottom) -->
        <div class="dot-matrix-orange" :style="maskStyle" aria-hidden="true" />
        <!-- Normal dots layer (top) -->
        <div class="dot-matrix-normal" aria-hidden="true" />
    </div>
</template>

<style scoped>
.dot-matrix-container {
    position: fixed;
    inset: 0;
    width: 100%;
    height: 100vh;
    pointer-events: none;
    overflow: hidden;
}

.dot-matrix-orange {
    position: absolute;
    inset: 0;
    background-image: url('../../../images/public/dots-orange.svg');
    background-repeat: repeat;
    transition: opacity 0.3s ease;

    /* Radial gradient mask that follows the mouse */
    mask-image: radial-gradient(
        circle 150px at var(--mouse-x) var(--mouse-y),
        rgba(0, 0, 0, 1) 0%,
        rgba(0, 0, 0, 0.8) 30%,
        rgba(0, 0, 0, 0.4) 60%,
        rgba(0, 0, 0, 0) 100%
    );
    -webkit-mask-image: radial-gradient(
        circle 150px at var(--mouse-x) var(--mouse-y),
        rgba(0, 0, 0, 1) 0%,
        rgba(0, 0, 0, 0.8) 30%,
        rgba(0, 0, 0, 0.4) 60%,
        rgba(0, 0, 0, 0) 100%
    );
    mask-size: 100% 100%;
    -webkit-mask-size: 100% 100%;
    mask-repeat: no-repeat;
    -webkit-mask-repeat: no-repeat;
}

.dot-matrix-normal {
    position: absolute;
    inset: 0;
    background-repeat: repeat;
}

/* Light theme */
.dot-matrix-normal {
    background-image: url('../../../images/public/dots-light.svg');
}

/* Dark theme */
:global(.dark) .dot-matrix-normal {
    background-image: url('../../../images/public/dots-dark.svg');
}

/* System preference dark */
@media (prefers-color-scheme: dark) {
    :global(html:not(.light)) .dot-matrix-normal {
        background-image: url('../../../images/public/dots-dark.svg');
    }
}

/* System preference light */
@media (prefers-color-scheme: light) {
    :global(html:not(.dark)) .dot-matrix-normal {
        background-image: url('../../../images/public/dots-light.svg');
    }
}
</style>