<script setup lang="ts">
import IconToggleButton from '@/components/ui/IconToggleButton.vue';
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

const ariaLabel = computed(() => `Current language: ${currentLanguage.value}. Click to switch to ${nextLanguageLabel.value}`);

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
    <IconToggleButton :icon="Languages" :aria-label="ariaLabel" :tooltip="currentLanguage" @click="toggleLanguage" />
</template>
