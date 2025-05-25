<script setup lang="ts">
import MagnifyingGlassRegular from '@/components/font-awesome/MagnifyingGlassRegular.vue';
import BlackButton from '@/components/public/Ui/Button/BlackButton.vue';

defineProps({
    placeholder: {
        type: String,
        default: 'Rechercher',
    },
});

const emit = defineEmits(['click']);

const handleClick = () => {
    emit('click');
};
</script>

<template>
    <button @click="handleClick" class="relative flex w-64 items-center gap-4 rounded-full bg-gray-200 pe-6 transition-colors hover:bg-gray-300">
        <BlackButton class="w-12">
            <MagnifyingGlassRegular class="absolute size-4 fill-white" />
        </BlackButton>
        <div class="w-full py-2 pr-4 text-left text-gray-600">
            {{ placeholder }}
        </div>
    </button>
</template>

<style scoped></style>
