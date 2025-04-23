<script setup lang="ts">
import { computed, ref } from 'vue';

interface Props {
    text: string;
    active?: boolean;
    to?: string;
}

const props = withDefaults(defineProps<Props>(), {
    active: false,
    to: '',
});

const emit = defineEmits(['click']);
const isHovered = ref(false);

const handleClick = () => {
    emit('click');
};

const indicatorClass = computed(() => {
    if (props.active) return 'bg-primary';
    if (isHovered.value) return 'bg-border';
    return 'bg-transparent';
});
</script>

<template>
    <div @click="handleClick" @mouseenter="isHovered = true" @mouseleave="isHovered = false" class="flex cursor-pointer gap-12">
        <div class="w-1 transition-colors" :class="indicatorClass"></div>
        <div class="text-2xl">{{ text }}</div>
    </div>
</template>
