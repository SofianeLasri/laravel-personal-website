<script setup lang="ts">
import { computed } from 'vue';

export interface BaseButtonProps {
    variant?: 'primary' | 'black' | 'white' | 'active' | 'light';
    size?: 'sm' | 'md';
    as?: 'button' | 'link';
    type?: 'button' | 'submit' | 'reset';
    href?: string;
    target?: '_self' | '_blank' | '_parent' | '_top';
    title?: string;
    disabled?: boolean;
    loading?: boolean;
}

const props = withDefaults(defineProps<BaseButtonProps>(), {
    variant: 'primary',
    size: 'md',
    as: 'button',
    type: 'button',
    target: '_self',
    title: '',
    disabled: false,
    loading: false,
});

defineOptions({
    inheritAttrs: false,
});

const variantClasses = {
    primary: {
        base: 'bg-atomic-tangerine-400 text-white dark:bg-atomic-tangerine-500 dark:text-gray-990',
        hover: 'hover:bg-atomic-tangerine-600 dark:hover:bg-atomic-tangerine-400',
        spinner: 'border-white border-t-transparent dark:border-gray-990 dark:border-t-transparent',
    },
    black: {
        base: 'bg-black text-white dark:bg-gray-100 dark:text-gray-990',
        hover: 'hover:bg-gray-950 dark:hover:bg-gray-200',
        spinner: 'border-white border-t-transparent dark:border-gray-990 dark:border-t-transparent',
    },
    white: {
        base: 'border bg-white text-black dark:bg-gray-990 dark:text-gray-100',
        hover: 'hover:bg-gray-100 dark:hover:bg-gray-975',
        spinner: 'border-black border-t-transparent dark:border-gray-100 dark:border-t-transparent',
    },
    active: {
        base: 'bg-atomic-tangerine-200 text-black dark:bg-atomic-tangerine-700 dark:text-gray-100',
        hover: 'hover:bg-atomic-tangerine-400 dark:hover:bg-atomic-tangerine-600',
        spinner: 'border-black border-t-transparent dark:border-gray-100 dark:border-t-transparent',
    },
    light: {
        base: 'bg-gray-200 text-black dark:bg-gray-800 dark:text-gray-100',
        hover: 'hover:bg-gray-300 dark:hover:bg-gray-700',
        spinner: 'border-black border-t-transparent dark:border-gray-100 dark:border-t-transparent',
    },
};

const sizeClasses = {
    sm: 'h-8 px-4 gap-2.5',
    md: 'h-12 px-6 gap-3',
};

const isDisabled = computed(() => props.disabled || props.loading);

const buttonClasses = computed(() => {
    const variant = variantClasses[props.variant];
    const size = sizeClasses[props.size];
    const base = 'flex flex-shrink-0 items-center justify-center rounded-full';

    const classes = [base, variant.base, size];

    if (!isDisabled.value) {
        classes.push('cursor-pointer', variant.hover);
    } else if (props.as === 'link') {
        classes.push('cursor-not-allowed opacity-70');
    }

    if (props.as === 'link') {
        classes.push('no-underline focus:border-none');
    }

    return classes;
});

const spinnerClasses = computed(() => {
    const variant = variantClasses[props.variant];
    return `inline-block h-4 w-4 animate-spin rounded-full border-2 ${variant.spinner}`;
});

const handleClick = (event: MouseEvent) => {
    if (props.as === 'link' && isDisabled.value) {
        event.preventDefault();
    }
};
</script>

<template>
    <component
        :is="as === 'link' ? 'a' : 'button'"
        :type="as === 'button' ? type : undefined"
        :href="as === 'link' && !isDisabled ? href : undefined"
        :target="as === 'link' && !isDisabled ? target : undefined"
        :title="as === 'link' ? title : undefined"
        :disabled="as === 'button' ? isDisabled : undefined"
        :aria-busy="loading"
        :aria-disabled="as === 'link' ? isDisabled : undefined"
        :role="as === 'link' ? 'button' : undefined"
        :class="buttonClasses"
        v-bind="$attrs"
        @click="handleClick"
    >
        <span v-if="loading" :class="spinnerClasses"></span>
        <slot></slot>
    </component>
</template>

<style scoped></style>
