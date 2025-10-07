<script setup lang="ts">
import { useRoute } from '@/composables/useRoute';
import { useTranslation } from '@/composables/useTranslation';
import axios from 'axios';
import { Languages } from 'lucide-vue-next';
import { computed } from 'vue';

const route = useRoute();
const { locale, t } = useTranslation();

const currentLanguage = computed(() => {
    return locale.value === 'fr' ? t('navigation.french') : t('navigation.english');
});

const nextLanguage = computed(() => {
    return locale.value === 'fr' ? 'en' : 'fr';
});

const nextLanguageLabel = computed(() => {
    return nextLanguage.value === 'fr' ? t('navigation.french') : t('navigation.english');
});

async function toggleLanguage() {
    try {
        await axios.post(route('public.set-language'), { language: nextLanguage.value });
        if (typeof window !== 'undefined') {
            window.location.reload();
        }
    } catch (error) {
        console.error('Failed to set language:', error);
    }
}
</script>

<template>
    <button
        class="group relative inline-flex items-center justify-center rounded-lg p-2 transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
        :aria-label="`Current language: ${currentLanguage}. Click to switch to ${nextLanguageLabel}`"
        type="button"
        @click="toggleLanguage"
    >
        <Languages class="h-5 w-5 text-gray-700 transition-transform group-hover:scale-110 dark:text-gray-300" />

        <!-- Tooltip -->
        <span
            class="absolute -bottom-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition-opacity group-hover:opacity-100 dark:bg-gray-100 dark:text-gray-900"
        >
            {{ currentLanguage }}
        </span>
    </button>
</template>
