<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Globe, X } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const page = usePage();
const locale = computed(() => (page.props.locale as string) || 'fr');

const isVisible = ref(false);
const isDismissed = ref(false);

const browserLanguage = computed(() => page.props.browserLanguage as string | null);

const shouldShowPopup = computed(() => {
    return !isDismissed.value && browserLanguage.value && browserLanguage.value !== 'fr' && locale.value === 'fr';
});

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
    isDismissed.value = true;
    isVisible.value = false;
    if (typeof window !== 'undefined') {
        localStorage.setItem('language_popup_dismissed', 'true');
    }
};

const acceptTranslation = () => {
    setLanguage('en');
};

onMounted(() => {
    if (typeof window !== 'undefined') {
        const dismissed = localStorage.getItem('language_popup_dismissed');
        if (dismissed === 'true') {
            isDismissed.value = true;
            return;
        }

        if (shouldShowPopup.value) {
            setTimeout(() => {
                isVisible.value = true;
            }, 2000);
        }
    }
});
</script>

<template>
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="transform translate-y-full opacity-0"
        enter-to-class="transform translate-y-0 opacity-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="transform translate-y-0 opacity-100"
        leave-to-class="transform translate-y-full opacity-0"
    >
        <div
            v-if="isVisible && shouldShowPopup"
            class="fixed right-4 bottom-4 z-50 max-w-sm rounded-lg border border-gray-200 bg-white p-4 shadow-xl backdrop-blur-sm dark:border-gray-700 dark:bg-gray-900"
        >
            <div class="flex items-start gap-3">
                <div class="bg-atomic-tangerine-100 dark:bg-atomic-tangerine-900 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full">
                    <Globe class="text-atomic-tangerine-600 dark:text-atomic-tangerine-400 h-4 w-4" />
                </div>

                <div class="min-w-0 flex-1">
                    <p class="mb-2 text-sm font-medium text-gray-900 dark:text-gray-100">Current language: French</p>
                    <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">Would you like to translate this page to English?</p>

                    <div class="flex gap-2">
                        <button
                            @click="acceptTranslation"
                            class="bg-atomic-tangerine-400 hover:bg-atomic-tangerine-500 rounded-md px-3 py-1.5 text-xs font-medium text-white shadow-sm transition-colors duration-200"
                        >
                            Yes, English
                        </button>
                        <button
                            @click="dismissPopup"
                            class="px-3 py-1.5 text-xs font-medium text-gray-600 transition-colors duration-200 hover:text-gray-800 dark:text-gray-300 dark:hover:text-gray-100"
                        >
                            No, thanks
                        </button>
                    </div>
                </div>

                <button
                    @click="dismissPopup"
                    class="hover:text-atomic-tangerine-600 dark:hover:text-atomic-tangerine-400 h-6 w-6 flex-shrink-0 text-gray-400 transition-colors duration-200"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>
        </div>
    </Transition>
</template>
