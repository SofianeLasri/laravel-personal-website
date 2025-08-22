<script setup lang="ts">
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
    <button
        @click="cycleTheme"
        class="group relative inline-flex items-center justify-center rounded-lg p-2 transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
        :aria-label="`Current theme: ${appearance}. Click to change theme`"
        type="button"
    >
        <component :is="currentIcon" class="h-5 w-5 text-gray-700 transition-transform group-hover:scale-110 dark:text-gray-300" />

        <!-- Tooltip -->
        <span
            class="absolute -bottom-8 left-1/2 -translate-x-1/2 rounded bg-gray-900 px-2 py-1 text-xs whitespace-nowrap text-white opacity-0 transition-opacity group-hover:opacity-100 dark:bg-gray-100 dark:text-gray-900"
        >
            {{ appearance === 'system' ? `System (${currentThemeDisplay})` : appearance }}
        </span>
    </button>
</template>
