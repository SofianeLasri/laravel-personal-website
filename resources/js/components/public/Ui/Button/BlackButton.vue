<script setup lang="ts">
defineProps({
    type: {
        type: String as () => 'button' | 'submit' | 'reset',
        default: 'button',
        validator: (value: string) => ['button', 'submit', 'reset'].includes(value),
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

defineOptions({
    inheritAttrs: false,
});
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        :aria-busy="loading"
        class="flex h-12 flex-shrink-0 cursor-pointer items-center justify-center gap-3 rounded-full bg-black px-6 text-white hover:bg-gray-950 dark:bg-gray-100 dark:text-gray-990 dark:hover:bg-gray-200"
        v-bind="$attrs"
    >
        <span v-if="loading" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent dark:border-gray-990 dark:border-t-transparent"></span>

        <slot></slot>
    </button>
</template>

<style scoped></style>
