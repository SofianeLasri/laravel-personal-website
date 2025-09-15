<script setup lang="ts">
import MagnifyingGlassRegular from '@/components/font-awesome/MagnifyingGlassRegular.vue';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

defineProps({
    placeholder: {
        type: String,
        default: 'Rechercher',
    },
});

const emit = defineEmits(['click']);

const handleClick = () => {
    emit('click');
};
</script>

<template>
    <button
        class="relative flex w-64 items-center gap-4 rounded-full bg-gray-200 pe-6 transition-all hover:bg-gray-300 dark:border dark:border-gray-700/50 dark:bg-gray-800/90 dark:shadow-[0_2px_8px_rgba(0,0,0,0.3)] dark:hover:bg-gray-700/90"
        @click="handleClick"
    >
        <div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-black transition-all dark:bg-white dark:shadow-sm">
            <MagnifyingGlassRegular class="dark:fill-gray-990 size-4 fill-white" />
        </div>
        <div class="w-full py-2 pr-4 text-left text-gray-600 dark:text-gray-300">
            {{ t('navigation.search_placeholder') }}
        </div>
    </button>
</template>

<style scoped></style>
