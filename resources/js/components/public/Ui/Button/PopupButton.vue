<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

export interface PopupButtonProps {
    variant?: 'primary' | 'secondary' | 'ghost';
    as?: 'button' | 'link' | 'inertia-link';
    type?: 'button' | 'submit' | 'reset';
    href?: string;
    disabled?: boolean;
}

const props = withDefaults(defineProps<PopupButtonProps>(), {
    variant: 'primary',
    as: 'button',
    type: 'button',
    href: undefined,
    disabled: false,
});

defineOptions({
    inheritAttrs: false,
});

const variantClasses = {
    primary: {
        base: 'bg-atomic-tangerine-400 text-white shadow-sm',
        hover: 'hover:bg-atomic-tangerine-500',
    },
    secondary: {
        base: 'border border-gray-300 bg-white text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200',
        hover: 'hover:bg-gray-50 dark:hover:bg-gray-700',
    },
    ghost: {
        base: 'text-gray-600 dark:text-gray-300',
        hover: 'hover:text-gray-800 dark:hover:text-gray-100',
    },
};

const isDisabled = computed(() => props.disabled);

const buttonClasses = computed(() => {
    const variant = variantClasses[props.variant];
    const base = 'no-glow flex items-center justify-center rounded-md px-3 py-1.5 text-xs font-medium transition-colors duration-200';

    const classes = [base, variant.base];

    if (!isDisabled.value) {
        classes.push('cursor-pointer', variant.hover);
    } else {
        classes.push('cursor-not-allowed opacity-50');
    }

    if (props.as === 'link') {
        classes.push('no-underline');
    }

    return classes;
});

const handleClick = (event: MouseEvent) => {
    if ((props.as === 'link' || props.as === 'inertia-link') && isDisabled.value) {
        event.preventDefault();
    }
};

const componentIs = computed(() => {
    if (props.as === 'inertia-link') return Link;
    if (props.as === 'link') return 'a';
    return 'button';
});
</script>

<template>
    <component
        :is="componentIs"
        :type="as === 'button' ? type : undefined"
        :href="(as === 'link' || as === 'inertia-link') && !isDisabled ? href : undefined"
        :disabled="as === 'button' ? isDisabled : undefined"
        :aria-disabled="(as === 'link' || as === 'inertia-link') ? isDisabled : undefined"
        :role="(as === 'link' || as === 'inertia-link') ? 'button' : undefined"
        :class="buttonClasses"
        v-bind="$attrs"
        @click="handleClick"
    >
        <slot></slot>
    </component>
</template>

<style scoped></style>
