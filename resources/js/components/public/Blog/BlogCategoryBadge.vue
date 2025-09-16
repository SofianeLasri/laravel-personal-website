<script setup lang="ts">
interface Props {
    category: {
        name: string;
        color: string;
    };
    size?: 'sm' | 'md';
}

const props = withDefaults(defineProps<Props>(), {
    size: 'md',
});

const getBadgeClasses = (color: string) => {
    const baseClasses = 'flex items-center justify-center gap-2.5 rounded-lg px-2 py-1';
    const sizeClasses = {
        sm: 'text-xs',
        md: 'text-sm',
    };

    const colorClasses = {
        red: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        blue: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        green: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        yellow: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        purple: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        pink: 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200',
        orange: 'bg-atomic-tangerine-100 text-atomic-tangerine-800 dark:bg-atomic-tangerine-900 dark:text-atomic-tangerine-200',
        gray: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
    };

    const colorClass = colorClasses[color as keyof typeof colorClasses] || colorClasses.gray;
    const sizeClass = sizeClasses[props.size];

    return `${baseClasses} ${colorClass} ${sizeClass}`;
};
</script>

<template>
    <div :class="getBadgeClasses(category.color)">
        <div class="justify-center">{{ category.name }}</div>
    </div>
</template>