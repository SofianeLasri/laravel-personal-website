<script setup lang="ts">
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

type Theme = 'light' | 'dark' | 'system';

const theme = ref<Theme>('system');
const systemPrefersDark = ref(false);

const currentIcon = computed(() => {
    if (theme.value === 'light') return Sun;
    if (theme.value === 'dark') return Moon;
    return Monitor;
});

const currentThemeDisplay = computed(() => {
    if (theme.value === 'system') {
        return systemPrefersDark.value ? 'dark' : 'light';
    }
    return theme.value;
});

function updateThemeClass() {
    const root = document.documentElement;

    // Remove existing theme classes
    root.classList.remove('light', 'dark');

    if (theme.value === 'light') {
        root.classList.add('light');
    } else if (theme.value === 'dark') {
        root.classList.add('dark');
    }
    // If 'system', let media query handle it (no class needed)
}

function cycleTheme() {
    const themes: Theme[] = ['system', 'light', 'dark'];
    const currentIndex = themes.indexOf(theme.value);
    const nextIndex = (currentIndex + 1) % themes.length;

    theme.value = themes[nextIndex];

    // Save preference
    if (theme.value === 'system') {
        localStorage.removeItem('theme');
    } else {
        localStorage.setItem('theme', theme.value);
    }

    updateThemeClass();
}

onMounted(() => {
    // Check system preference
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    systemPrefersDark.value = mediaQuery.matches;

    // Listen for system theme changes
    mediaQuery.addEventListener('change', (e) => {
        systemPrefersDark.value = e.matches;
        if (theme.value === 'system') {
            updateThemeClass();
        }
    });

    // Load saved preference
    const savedTheme = localStorage.getItem('theme') as Theme | null;
    if (savedTheme && ['light', 'dark'].includes(savedTheme)) {
        theme.value = savedTheme;
    }

    // Apply initial theme
    updateThemeClass();

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
        :aria-label="`Current theme: ${theme}. Click to change theme`"
        type="button"
    >
        <component :is="currentIcon" class="h-5 w-5 text-gray-700 transition-transform group-hover:scale-110 dark:text-gray-300" />

        <!-- Tooltip -->
        <span
            class="absolute -bottom-8 left-1/2 -translate-x-1/2 rounded bg-gray-900 px-2 py-1 text-xs whitespace-nowrap text-white opacity-0 transition-opacity group-hover:opacity-100 dark:bg-gray-100 dark:text-gray-900"
        >
            {{ theme === 'system' ? `System (${currentThemeDisplay})` : theme }}
        </span>
    </button>
</template>
