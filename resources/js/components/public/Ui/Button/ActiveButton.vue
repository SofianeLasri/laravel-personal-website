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
        class="bg-atomic-tangerine-200 hover:bg-atomic-tangerine-400 flex h-12 flex-shrink-0 cursor-pointer items-center justify-center gap-3 rounded-full px-6 text-black"
        v-bind="$attrs"
    >
        <span v-if="loading" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-black border-t-transparent"></span>

        <slot></slot>
    </button>
</template>

<style scoped></style>
