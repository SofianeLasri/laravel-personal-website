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
        class="flex h-12 flex-shrink-0 items-center justify-center gap-3 rounded-full bg-gray-200 px-6 text-black no-underline focus:outline-none"
        :class="{
            'cursor-pointer hover:bg-gray-300': !disabled && !loading,
            'cursor-not-allowed opacity-70': disabled || loading,
        }"
        role="button"
        v-bind="$attrs"
        @click="disabled || loading ? $event.preventDefault() : null"
    >
        <span v-if="loading" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-black border-t-transparent"></span>

        <slot></slot>
    </a>
</template>

<style scoped></style>
