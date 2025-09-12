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
        class="dark:from-atomic-tangerine-800/60 dark:to-atomic-tangerine-950/40 dark:border-atomic-tangerine-600/20 flex cursor-pointer items-start justify-start gap-4 self-stretch rounded-2xl bg-black p-2 shadow-lg dark:border dark:bg-gradient-to-br dark:shadow-[0_0_15px_rgba(247,142,87,0.15)]"
        @click="emit('click')"
    >
        <div
            class="dark:border-atomic-tangerine-600/30 borderbg-white flex size-20 shrink-0 items-center justify-center rounded-xl p-2 dark:bg-white"
        >
            <picture class="flex h-full w-full items-center justify-center">
                <source :srcset="experience.logo.webp.thumbnail" type="image/webp" />
                <img :src="experience.logo.avif.thumbnail" :alt="experience.title" class="object-contain" loading="lazy" />
            </picture>
        </div>
        <div class="flex flex-col content-between gap-4">
            <div class="flex flex-col items-start justify-start gap-px self-stretch">
                <div class="dark:text-atomic-tangerine-100 justify-center self-stretch font-bold text-white">{{ experience.title }}</div>
                <div class="dark:text-atomic-tangerine-200/70 justify-center self-stretch text-sm font-medium text-gray-300">
                    {{ experience.organizationName }}
                </div>
            </div>
            <div class="dark:text-atomic-tangerine-300/60 justify-center self-stretch text-sm font-medium text-gray-300">
                {{ experience.startedAtFormatted }} -
                {{ experience.endedAtFormatted || t('career.today') }}
            </div>
        </div>
    </div>
</template>
