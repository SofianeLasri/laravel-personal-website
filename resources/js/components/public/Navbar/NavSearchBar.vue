<script setup lang="ts">
import MagnifyingGlassRegular from '@/components/font-awesome/MagnifyingGlassRegular.vue';
import BlackButton from '@/components/public/Ui/Button/BlackButton.vue';
import { ref, watch } from 'vue';

const props = defineProps({
    placeholder: {
        type: String,
        default: 'Rechercher',
    },
    modelValue: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue', 'search']);

const searchQuery = ref(props.modelValue);

watch(
    () => props.modelValue,
    (newValue) => {
        searchQuery.value = newValue;
    },
);

const onInput = () => {
    emit('update:modelValue', searchQuery.value);
    emit('search', searchQuery.value);
};
</script>

<template>
    <div class="relative flex w-64 items-center gap-4 rounded-full bg-gray-200 pe-6">
        <BlackButton class="w-12">
            <MagnifyingGlassRegular class="absolute size-4 fill-white" />
        </BlackButton>
        <input
            type="text"
            :placeholder="placeholder"
            v-model="searchQuery"
            class="w-full border-none bg-transparent py-2 pr-4 focus:outline-none"
            data-form-type="query"
            @input="onInput"
        />
    </div>
</template>

<style scoped></style>
