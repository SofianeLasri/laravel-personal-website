<script setup lang="ts">
import PopupButton from '@/components/public/Ui/Button/PopupButton.vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Globe } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();
const locale = computed(() => (page.props.locale as string) || 'fr');

const emit = defineEmits<{
    dismiss: [];
}>();

const browserLanguage = computed(() => page.props.browserLanguage as string | null);

const shouldShow = () => {
    if (typeof window === 'undefined') return false;
    const dismissed = localStorage.getItem('language_popup_dismissed');
    return dismissed !== 'true' && browserLanguage.value !== null && browserLanguage.value !== 'fr' && locale.value === 'fr';
};

const setLanguage = async (language: string) => {
    try {
        await axios.post(route('public.set-language'), { language });
        if (typeof window !== 'undefined') {
            window.location.reload();
        }
    } catch (error) {
        console.error('Failed to set language:', error);
    }
};

const dismissPopup = () => {
    if (typeof window !== 'undefined') {
        localStorage.setItem('language_popup_dismissed', 'true');
    }
    emit('dismiss');
};

const acceptTranslation = () => {
    void setLanguage('en');
    dismissPopup();
};

defineExpose({
    shouldShow,
});
</script>

<template>
    <div class="flex items-start gap-3 p-4">
        <div class="bg-atomic-tangerine-100 dark:bg-atomic-tangerine-900 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full">
            <Globe class="text-atomic-tangerine-600 dark:text-atomic-tangerine-400 h-4 w-4" />
        </div>

        <div class="min-w-0 flex-1">
            <p class="mb-2 text-sm font-medium text-gray-900 dark:text-gray-100">Current language: French</p>
            <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">The website is also available in english, would you like to switch?</p>

            <div class="flex gap-2">
                <PopupButton variant="primary" @click="acceptTranslation"> Yes, English </PopupButton>
                <PopupButton variant="ghost" @click="dismissPopup"> No, thanks </PopupButton>
            </div>
        </div>
    </div>
</template>
