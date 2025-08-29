<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';

const mouseX = ref(-1000);
const mouseY = ref(-1000);
const isHovering = ref(false);
const isDarkMode = ref(false);

// Check if device is mobile
const isMobile = computed(() => {
    if (typeof window === 'undefined') return false;
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || window.innerWidth < 768;
});

// Should show effect
const showEffect = computed(() => {
    return !isMobile.value && isHovering.value;
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
    if (isMobile.value) return;

    // Use clientX/clientY since container is now fixed
    mouseX.value = e.clientX;
    mouseY.value = e.clientY;
    isHovering.value = true;
};

const handleMouseLeave = () => {
    isHovering.value = false;
};

// Update dark mode state
const updateDarkMode = () => {
    isDarkMode.value = document.documentElement.classList.contains('dark');
};

onMounted(() => {
    if (!isMobile.value) {
        window.addEventListener('mousemove', handleMouseMove);
        window.addEventListener('mouseleave', handleMouseLeave);
    }

    // Initial dark mode check
    updateDarkMode();

    // Watch for theme changes
    const observer = new MutationObserver(() => {
        updateDarkMode();
    });

    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });

    onUnmounted(() => {
        observer.disconnect();
    });
});

onUnmounted(() => {
    window.removeEventListener('mousemove', handleMouseMove);
    window.removeEventListener('mouseleave', handleMouseLeave);
});
</script>

<template>
    <div class="dot-matrix-container">
        <!-- Base dots layer -->
        <div class="dot-matrix-base" :class="{ 'dot-matrix-dark': isDarkMode, 'dot-matrix-light': !isDarkMode }" aria-hidden="true" />
        <!-- Orange dots overlay with inverted mask -->
        <div
            class="dot-matrix-orange"
            :class="{ 'dot-matrix-orange-dark': isDarkMode, 'dot-matrix-orange-light': !isDarkMode }"
            :style="maskStyle"
            aria-hidden="true"
        />
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

.dot-matrix-base {
    position: absolute;
    inset: 0;
    background-repeat: repeat;
}

.dot-matrix-orange {
    position: absolute;
    inset: 0;
    background-repeat: repeat;
    transition: opacity 0.3s ease;
    mix-blend-mode: screen; /* Use screen blend mode for better visibility */

    /* Radial gradient mask that follows the mouse */
    mask-image: radial-gradient(
        circle 150px at var(--mouse-x) var(--mouse-y),
        rgba(0, 0, 0, 1) 0%,
        rgba(0, 0, 0, 0.9) 25%,
        rgba(0, 0, 0, 0.6) 50%,
        rgba(0, 0, 0, 0.2) 75%,
        rgba(0, 0, 0, 0) 100%
    );
    -webkit-mask-image: radial-gradient(
        circle 150px at var(--mouse-x) var(--mouse-y),
        rgba(0, 0, 0, 1) 0%,
        rgba(0, 0, 0, 0.9) 25%,
        rgba(0, 0, 0, 0.6) 50%,
        rgba(0, 0, 0, 0.2) 75%,
        rgba(0, 0, 0, 0) 100%
    );
    mask-size: 100% 100%;
    -webkit-mask-size: 100% 100%;
    mask-repeat: no-repeat;
    -webkit-mask-repeat: no-repeat;
}

/* Light theme base dots */
.dot-matrix-light {
    background-image: url('../../../images/public/dots-light.svg');
}

/* Dark theme base dots */
.dot-matrix-dark {
    background-image: url('../../../images/public/dots-dark.svg');
}

/* Orange dots for light theme */
.dot-matrix-orange-light {
    background-image: url('../../../images/public/dots-orange.svg');
    mix-blend-mode: multiply; /* Better blend for light theme */
}

/* Brighter orange dots for dark theme */
.dot-matrix-orange-dark {
    background-image: url('../../../images/public/dots-orange.svg');
}
</style>