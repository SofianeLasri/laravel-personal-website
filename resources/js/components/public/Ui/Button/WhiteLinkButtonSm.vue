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
        class="border-border flex h-8 flex-shrink-0 items-center justify-center gap-3 rounded-full border-1 bg-white px-4 text-black no-underline focus:border-none dark:bg-gray-990 dark:text-gray-100"
        :class="{
            'cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-975': !disabled && !loading,
            'cursor-not-allowed opacity-70': disabled || loading,
        }"
        role="button"
        :title="title"
        v-bind="$attrs"
        @click="disabled || loading ? $event.preventDefault() : null"
    >
        <span v-if="loading" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-black border-t-transparent dark:border-gray-100 dark:border-t-transparent"></span>

        <slot></slot>
    </a>
</template>

<style scoped></style>
