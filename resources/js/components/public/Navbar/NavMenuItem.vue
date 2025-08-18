<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface Props {
    text: string;
    active?: boolean;
    to?: string;
}

withDefaults(defineProps<Props>(), {
    active: false,
    to: '',
});
</script>

<template>
    <Link :href="to" class="hover:text-primary dark:hover:text-primary-400 flex cursor-pointer gap-12 py-2 transition-colors">
        <div class="w-1 opacity-0"></div>
        <div class="text-2xl dark:text-gray-200">{{ text }}</div>
    </Link>
</template>
