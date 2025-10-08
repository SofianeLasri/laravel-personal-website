<script setup lang="ts">
import IconToggleButton from '@/components/ui/IconToggleButton.vue';
import { useAppearance } from '@/composables/useAppearance';
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

type Appearance = 'light' | 'dark' | 'system';

const { appearance, updateAppearance } = useAppearance();
const systemPrefersDark = ref(false);

const currentIcon = computed(() => {
    if (appearance.value === 'light') return Sun;
    if (appearance.value === 'dark') return Moon;
    return Monitor;
});

const currentThemeDisplay = computed(() => {
    if (appearance.value === 'system') {
        return systemPrefersDark.value ? 'dark' : 'light';
    }
    return appearance.value;
});

const ariaLabel = computed(() => `Current theme: ${appearance.value}. Click to change theme`);

const tooltip = computed(() => {
    return appearance.value === 'system' ? `System (${currentThemeDisplay.value})` : appearance.value;
});

function cycleTheme() {
    const themes: Appearance[] = ['system', 'light', 'dark'];
    const currentIndex = themes.indexOf(appearance.value);
    const nextIndex = (currentIndex + 1) % themes.length;

    updateAppearance(themes[nextIndex]);
}

onMounted(() => {
    // Check system preference
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    systemPrefersDark.value = mediaQuery.matches;

    // Listen for system theme changes
    mediaQuery.addEventListener('change', (e) => {
        systemPrefersDark.value = e.matches;
    });

    // Remove no-transition class after initial load
    setTimeout(() => {
        document.documentElement.classList.remove('no-transition');
    }, 100);
});
</script>

<template>
    <IconToggleButton :icon="currentIcon" :aria-label="ariaLabel" :tooltip="tooltip" @click="cycleTheme" />
</template>
