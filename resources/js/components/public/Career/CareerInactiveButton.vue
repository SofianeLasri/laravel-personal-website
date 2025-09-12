<script setup lang="ts">
import { useTranslation } from '@/composables/useTranslation';
import { SSRExperience } from '@/types';

defineProps<{
    experience: SSRExperience;
}>();

const { t } = useTranslation();

const emit = defineEmits(['click']);
</script>

<template>
    <div
        class="flex cursor-pointer items-start justify-start gap-4 self-stretch rounded-2xl bg-white p-2 shadow-lg transition-all hover:scale-[1.02] dark:border dark:border-gray-700/30 dark:bg-gray-800/60 dark:shadow-[0_0_10px_rgba(0,0,0,0.2)] dark:hover:bg-gray-800/80"
        @click="emit('click')"
    >
        <div class="flex size-20 shrink-0 items-center justify-center rounded-xl border border-1 bg-white p-2 dark:border-gray-700/20 dark:bg-white">
            <picture class="flex h-full w-full items-center justify-center">
                <source :srcset="experience.logo.webp.thumbnail" type="image/webp" />
                <img :src="experience.logo.avif.thumbnail" :alt="experience.title" class="object-contain" loading="lazy" />
            </picture>
        </div>
        <div class="flex flex-col content-between gap-4">
            <div class="flex flex-col items-start justify-start gap-px self-stretch">
                <div class="justify-center self-stretch font-bold text-black dark:text-gray-200">{{ experience.title }}</div>
                <div class="justify-center self-stretch text-sm font-medium text-gray-800 dark:text-gray-400">
                    {{ experience.organizationName }}
                </div>
            </div>
            <div class="justify-center self-stretch text-sm font-medium text-gray-800 dark:text-gray-500">
                {{ experience.startedAtFormatted }} -
                {{ experience.endedAtFormatted || t('career.today') }}
            </div>
        </div>
    </div>
</template>
