<script setup lang="ts">
import { useTranslation } from '@/composables/useTranslation';
import { SSRCertification } from '@/types';

const { t } = useTranslation();

defineProps<{
    certification: SSRCertification;
}>();
</script>

<template>
    <a
        :href="certification.link ? certification.link : '#'"
        :class="{ 'pointer-events-none': !certification.link }"
        class="flex items-center justify-start gap-8 transition-transform hover:scale-105"
        target="_blank"
        rel="noopener noreferrer"
    >
        <div class="flex items-center justify-center">
            <picture v-if="certification.picture">
                <source :srcset="certification.picture.avif.medium" type="image/avif" />
                <source :srcset="certification.picture.webp.medium" type="image/webp" />
                <img
                    :src="certification.picture.webp.medium"
                    :alt="`${t('career.certifications')} ${certification.name}`"
                    class="size-32 object-contain"
                    loading="lazy"
                />
            </picture>
            <div v-else class="flex size-32 items-center justify-center rounded-lg bg-gray-200">
                <span class="text-sm text-gray-500">{{ t('career.certifications') }}</span>
            </div>
        </div>

        <div class="flex flex-col gap-2">
            <div class="text-design-system-title text-xl font-bold">{{ certification.name }}</div>
            <div class="flex flex-col gap-2">
                <div v-if="certification.level" class="text-design-system-paragraph justify-center text-sm font-semibold">
                    {{ t('career.level') }} : {{ certification.level }}
                </div>
                <div v-if="certification.score" class="text-design-system-paragraph justify-center text-sm font-semibold">
                    {{ t('career.score') }} : {{ certification.score }}
                </div>
                <div class="text-design-system-paragraph justify-center text-sm font-semibold">
                    {{ t('career.date') }} : {{ certification.dateFormatted }}
                </div>
            </div>
        </div>
    </a>
</template>
