<script setup lang="ts">
import type { Component } from 'vue';

interface Props {
    icon: Component;
    ariaLabel: string;
    tooltip: string;
}

defineProps<Props>();

const emit = defineEmits<{
    click: [];
}>();

const handleClick = () => {
    emit('click');
};
</script>

<template>
    <button
        class="group relative inline-flex items-center justify-center rounded-lg p-2 transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
        :aria-label="ariaLabel"
        type="button"
        @click="handleClick"
    >
        <component :is="icon" class="h-5 w-5 text-gray-700 transition-transform group-hover:scale-110 dark:text-gray-300" />

        <!-- Tooltip -->
        <span
            class="absolute -bottom-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition-opacity group-hover:opacity-100 dark:bg-gray-100 dark:text-gray-900"
        >
            {{ tooltip }}
        </span>
    </button>
</template>
