<script setup lang="ts">
import { useTranslation } from '@/composables/useTranslation';
import { SSRExperience } from '@/types';
import { Link } from '@inertiajs/vue3';

const { t } = useTranslation();

defineProps<{
    experience: SSRExperience;
}>();

const formatPeriod = (startDate: string, endDate: string | null) => {
    if (endDate) {
        return `${startDate} - ${endDate}`;
    }
    return `${startDate} - ${t('career.today')}`;
};
</script>

<template>
    <Link :href="`/certifications-career/${experience.slug}`" class="flex gap-4 rounded-2xl transition-transform hover:scale-105">
        <div
            class="flex size-20 flex-shrink-0 items-center justify-center gap-2.5 rounded-lg border bg-white p-3 shadow-[0px_0.25rem_0.5rem_0px_rgba(0,0,0,0.25)] md:p-4"
        >
            <img
                v-if="experience.logo"
                :src="experience.logo.webp.medium"
                :alt="`Logo ${experience.organizationName}`"
                class="h-full w-full object-contain"
                loading="eager"
            />
            <div v-else class="flex h-full w-full items-center justify-center rounded bg-gray-200">
                <span class="text-center text-xs text-gray-500">{{ experience.organizationName }}</span>
            </div>
        </div>
        <div class="flex flex-1 flex-col gap-4">
            <div class="flex flex-col gap-px">
                <div class="text-design-system-title justify-center text-base font-bold">{{ experience.title }}</div>
                <div class="text-design-system-paragraph justify-center text-sm font-semibold">{{ experience.organizationName }}</div>
            </div>
            <div class="text-design-system-paragraph justify-center text-sm font-normal">
                {{ formatPeriod(experience.startedAtFormatted, experience.endedAtFormatted) }}
            </div>
        </div>
    </Link>
</template>
