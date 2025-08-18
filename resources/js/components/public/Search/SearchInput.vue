<script setup lang="ts">
import MagnifyingGlassRegular from '@/components/font-awesome/MagnifyingGlassRegular.vue';
import BaseButton from '@/components/public/Ui/Button/BaseButton.vue';
import { useTranslation } from '@/composables/useTranslation';
import { X } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';

const props = defineProps<{
    modelValue: string;
    loading?: boolean;
    autofocus?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
    clear: [];
}>();

const { t } = useTranslation();
const inputRef = ref<HTMLInputElement | null>(null);

const handleInput = (event: Event) => {
    const target = event.target as HTMLInputElement;
    emit('update:modelValue', target.value);
};

const handleClear = () => {
    emit('update:modelValue', '');
    emit('clear');
    inputRef.value?.focus();
};

watch(
    () => props.autofocus,
    (autofocus) => {
        if (autofocus && inputRef.value) {
            inputRef.value.focus();
        }
    },
);

onMounted(() => {
    if (props.autofocus && inputRef.value) {
        inputRef.value.focus();
    }
});
</script>

<template>
    <div class="relative flex items-center gap-4 rounded-full bg-gray-100 pe-6 dark:bg-gray-800">
        <BaseButton variant="black" class="w-12" tabindex="-1">
            <MagnifyingGlassRegular class="absolute size-4 fill-white dark:fill-gray-900" />
        </BaseButton>
        <input
            ref="inputRef"
            type="text"
            :value="modelValue"
            @input="handleInput"
            :placeholder="t('search.placeholder')"
            class="flex-1 bg-transparent py-3 text-black outline-none placeholder:text-gray-600 dark:text-gray-100 dark:placeholder:text-gray-400"
            :disabled="loading"
        />
        <div class="flex items-center gap-2">
            <div v-if="loading" class="h-4 w-4 animate-spin rounded-full border-2 border-gray-400 border-t-transparent"></div>
            <button
                v-if="modelValue"
                @click="handleClear"
                class="rounded-full p-1 text-gray-600 hover:bg-gray-200 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                :aria-label="t('search.clear')"
            >
                <X class="h-4 w-4" />
            </button>
        </div>
    </div>
</template>
