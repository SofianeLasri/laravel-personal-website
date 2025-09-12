<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';

const mouseX = ref(0);
const mouseY = ref(0);
const isActive = ref(false);
const glowElements = ref<Set<HTMLElement>>(new Set());
const glowRadius = 150; // Radius of the glow effect in pixels

// Check if device is mobile
const isMobile = computed(() => {
    if (typeof window === 'undefined') return false;
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || window.innerWidth < 768;
});

// Check if a point is within the glow radius
const isPointInGlowRadius = (x: number, y: number): boolean => {
    const distance = Math.sqrt(Math.pow(mouseX.value - x, 2) + Math.pow(mouseY.value - y, 2));
    return distance <= glowRadius;
};

// Check if element's border intersects with glow radius
const doesBorderIntersectGlow = (rect: DOMRect): boolean => {
    // Check if any of the four edges of the element are within the glow radius
    const edges = [
        // Top edge
        { x1: rect.left, y1: rect.top, x2: rect.right, y2: rect.top },
        // Right edge
        { x1: rect.right, y1: rect.top, x2: rect.right, y2: rect.bottom },
        // Bottom edge
        { x1: rect.left, y1: rect.bottom, x2: rect.right, y2: rect.bottom },
        // Left edge
        { x1: rect.left, y1: rect.top, x2: rect.left, y2: rect.bottom },
    ];

    // Check multiple points along each edge
    for (const edge of edges) {
        const steps = 10; // Check 10 points along each edge
        for (let i = 0; i <= steps; i++) {
            const t = i / steps;
            const x = edge.x1 + (edge.x2 - edge.x1) * t;
            const y = edge.y1 + (edge.y2 - edge.y1) * t;

            if (isPointInGlowRadius(x, y)) {
                return true;
            }
        }
    }

    return false;
};

// Calculate the closest point on the element's border to the mouse
const getClosestBorderPoint = (rect: DOMRect): { x: number; y: number; distance: number } => {
    // Clamp mouse position to element bounds to find closest border point
    const closestX = Math.max(rect.left, Math.min(mouseX.value, rect.right));
    const closestY = Math.max(rect.top, Math.min(mouseY.value, rect.bottom));

    // If mouse is inside element, find closest edge
    if (mouseX.value >= rect.left && mouseX.value <= rect.right && mouseY.value >= rect.top && mouseY.value <= rect.bottom) {
        const distances = [
            { x: mouseX.value, y: rect.top, d: Math.abs(mouseY.value - rect.top) }, // top
            { x: rect.right, y: mouseY.value, d: Math.abs(mouseX.value - rect.right) }, // right
            { x: mouseX.value, y: rect.bottom, d: Math.abs(mouseY.value - rect.bottom) }, // bottom
            { x: rect.left, y: mouseY.value, d: Math.abs(mouseX.value - rect.left) }, // left
        ];

        const closest = distances.reduce((min, curr) => (curr.d < min.d ? curr : min));
        return { x: closest.x, y: closest.y, distance: closest.d };
    }

    const distance = Math.sqrt(Math.pow(mouseX.value - closestX, 2) + Math.pow(mouseY.value - closestY, 2));

    return { x: closestX, y: closestY, distance };
};

// Update gradient for elements near mouse
const updateGradients = () => {
    if (isMobile.value) return;

    // Select all potential elements with borders
    const elements = document.querySelectorAll<HTMLElement>(`
        .glow-border:not(.no-glow),
        .card:not(.no-glow),
        button:not(.no-glow),
        .btn:not(.no-glow),
        a.inline-flex:not(.no-glow),
        .group:not(.no-glow),
        [role="button"]:not(.no-glow),
        .border:not(.no-glow)
    `);

    elements.forEach((element) => {
        // Skip elements explicitly marked with no-glow
        if (element.classList.contains('no-glow')) {
            return;
        }

        // Check if element has any transparent or no-border classes
        const classes = Array.from(element.classList);
        const hasBorderTransparent = classes.some(
            (cls) =>
                cls === 'border-transparent' ||
                cls === 'border-none' ||
                cls === 'border-0' ||
                cls.endsWith(':border-transparent') ||
                cls.endsWith(':border-none') ||
                cls.endsWith(':border-0'),
        );

        if (hasBorderTransparent) {
            return;
        }

        const rect = element.getBoundingClientRect();

        if (doesBorderIntersectGlow(rect)) {
            const closest = getClosestBorderPoint(rect);

            // Calculate angle from element center to mouse
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;
            const angle = Math.atan2(mouseY.value - centerY, mouseX.value - centerX) * (180 / Math.PI);

            // Calculate opacity based on distance from border to mouse
            const opacity = Math.max(0, 1 - closest.distance / glowRadius);

            // Update CSS variables for this specific element
            element.style.setProperty('--glow-x', `${mouseX.value - rect.left}px`);
            element.style.setProperty('--glow-y', `${mouseY.value - rect.top}px`);
            element.style.setProperty('--glow-angle', `${angle}deg`);
            element.style.setProperty('--glow-opacity', `${opacity}`);

            if (!element.classList.contains('glow-active')) {
                element.classList.add('glow-active');
                glowElements.value.add(element);
            }
        } else {
            element.classList.remove('glow-active');
            element.style.removeProperty('--glow-x');
            element.style.removeProperty('--glow-y');
            element.style.removeProperty('--glow-angle');
            element.style.removeProperty('--glow-opacity');
            glowElements.value.delete(element);
        }
    });
};

const handleMouseMove = (e: MouseEvent) => {
    if (isMobile.value) return;

    mouseX.value = e.clientX;
    mouseY.value = e.clientY;
    isActive.value = true;

    // Update gradients with RAF for performance
    requestAnimationFrame(updateGradients);
};

const handleMouseLeave = (e: MouseEvent) => {
    // Check if mouse is really leaving the window
    if (e.clientY <= 0 || e.clientX <= 0 || e.clientX >= window.innerWidth || e.clientY >= window.innerHeight) {
        isActive.value = false;

        // Clear all glow effects
        glowElements.value.forEach((element) => {
            element.classList.remove('glow-active');
            element.style.removeProperty('--glow-x');
            element.style.removeProperty('--glow-y');
            element.style.removeProperty('--glow-angle');
            element.style.removeProperty('--glow-opacity');
        });
        glowElements.value.clear();
    }
};

const handleMouseEnter = () => {
    isActive.value = true;
};

onMounted(() => {
    if (!isMobile.value) {
        document.addEventListener('mousemove', handleMouseMove);
        document.addEventListener('mouseleave', handleMouseLeave);
        document.addEventListener('mouseenter', handleMouseEnter);
    }
});

onUnmounted(() => {
    document.removeEventListener('mousemove', handleMouseMove);
    document.removeEventListener('mouseleave', handleMouseLeave);
    document.removeEventListener('mouseenter', handleMouseEnter);

    // Clean up any remaining glow effects
    glowElements.value.forEach((element) => {
        element.classList.remove('glow-active');
        element.style.removeProperty('--glow-x');
        element.style.removeProperty('--glow-y');
        element.style.removeProperty('--glow-angle');
        element.style.removeProperty('--glow-opacity');
    });
});
</script>

<template>
    <!-- This component only manages the global state, no visual output -->
    <div />
</template>
