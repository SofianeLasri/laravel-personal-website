<script setup lang="ts">
defineProps({
    href: {
        type: String,
        required: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    target: {
        type: String,
        default: '_self',
        validator: (value: string) => ['_self', '_blank', '_parent', '_top'].includes(value),
    },
    title: {
        type: String,
        default: '',
    },
});

defineOptions({
    inheritAttrs: false,
});
</script>

<template>
    <a
        :href="disabled || loading ? undefined : href"
        :target="disabled || loading ? undefined : target"
        :aria-busy="loading"
        :aria-disabled="disabled || loading"
        class="bg-atomic-tangerine-400 flex h-12 flex-shrink-0 items-center justify-center gap-3 rounded-full px-6 text-white no-underline focus:border-none dark:bg-atomic-tangerine-500 dark:text-gray-990"
        :class="{
            'hover:bg-atomic-tangerine-600 cursor-pointer dark:hover:bg-atomic-tangerine-400': !disabled && !loading,
            'cursor-not-allowed opacity-70': disabled || loading,
        }"
        role="button"
        :title="title"
        v-bind="$attrs"
        @click="disabled || loading ? $event.preventDefault() : null"
    >
        <span v-if="loading" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent dark:border-gray-990 dark:border-t-transparent"></span>

        <slot></slot>
    </a>
</template>

<style scoped></style>
