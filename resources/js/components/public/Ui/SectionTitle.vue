<script setup lang="ts">
defineProps<{
    title: string;
}>();
</script>

<template>
    <h3 class="text-design-system-title text-2xl font-bold">{{ title }}</h3>
</template>
