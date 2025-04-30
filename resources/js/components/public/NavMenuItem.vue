<script setup lang="ts">
interface Props {
    text: string;
    active?: boolean;
    to?: string;
}

withDefaults(defineProps<Props>(), {
    active: false,
    to: '',
});

const emit = defineEmits(['click']);

const handleClick = () => {
    emit('click');
};
</script>

<template>
    <div @click="handleClick" class="flex cursor-pointer gap-12 py-2">
        <div class="w-1 opacity-0"></div>
        <div class="text-2xl">{{ text }}</div>
    </div>
</template>
