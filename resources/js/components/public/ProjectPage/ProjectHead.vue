<script setup lang="ts">
import LightShape from '@/components/public/LightShape.vue';
import BaseButton from '@/components/public/Ui/Button/BaseButton.vue';
import { useTranslation } from '@/composables/useTranslation';
import { SSRFullCreation } from '@/types';
import { computed } from 'vue';

const props = defineProps<{
    creation: SSRFullCreation;
}>();

const { t } = useTranslation();

const formattedPeriod = computed(() => {
    const startDate = new Date(props.creation.startedAt);

    if (!props.creation.endedAt) {
        return `${props.creation.startedAtFormatted} - ${t('project.ongoing')}`;
    }

    const endDate = new Date(props.creation.endedAt);

    if (startDate.getFullYear() === endDate.getFullYear() && startDate.getMonth() === endDate.getMonth()) {
        return props.creation.startedAtFormatted;
    }

    return `${props.creation.startedAtFormatted} - ${props.creation.endedAtFormatted}`;
});
</script>
<template>
    <div class="flex items-center justify-between gap-8 xl:gap-32">
        <div class="flex grow flex-col gap-16">
            <div class="flex flex-col gap-6 self-stretch">
                <div class="inline-flex justify-between self-stretch">
                    <div
                        class="bg-gray-0 flex size-24 items-center justify-center gap-2.5 overflow-hidden rounded-lg border p-4"
                        data-testid="project-status"
                    >
                        <img
                            :src="creation.logo.webp.small"
                            :alt="t('project.project_logo_alt')"
                            class="h-full w-full object-cover"
                            loading="eager"
                        />
                    </div>
                    <div v-if="creation.endedAt" class="flex flex-col items-end">
                        <div class="text-design-system-paragraph justify-center font-medium">{{ t('project.created_in') }}</div>
                        <div class="text-design-system-paragraph justify-center text-2xl font-bold">
                            {{ new Date(creation.endedAt).getFullYear() }}
                        </div>
                    </div>
                    <div v-else class="flex flex-col items-end">
                        <div class="text-design-system-paragraph justify-center font-medium">{{ t('project.realization') }}</div>
                        <div class="text-design-system-paragraph justify-center text-2xl font-bold">{{ t('project.ongoing') }}</div>
                    </div>
                </div>
                <div class="flex flex-col gap-2 self-stretch">
                    <div class="text-design-system-title justify-center self-stretch text-5xl font-bold" data-testid="project-name">
                        {{ creation.name }}
                    </div>
                    <div class="text-design-system-paragraph justify-center self-stretch text-xl font-bold">
                        {{ t(`projects.types.${creation.type}`) }}
                    </div>
                </div>
                <div class="text-design-system-paragraph justify-center self-stretch text-xl font-normal">
                    {{ creation.shortDescription }}
                </div>
                <div class="flex flex-col gap-1 self-stretch">
                    <div class="text-design-system-paragraph justify-center text-sm font-medium">{{ t('project.period') }}</div>
                    <div class="text-design-system-paragraph justify-center text-base font-normal">
                        {{ formattedPeriod }}
                    </div>
                </div>
            </div>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center" data-testid="project-links">
                <BaseButton
                    v-if="creation.externalUrl"
                    variant="black"
                    as="link"
                    :href="creation.externalUrl"
                    data-testid="demo-link"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    {{ t('project.visit_website') }}
                </BaseButton>
                <BaseButton
                    v-if="creation.sourceCodeUrl"
                    variant="white"
                    as="link"
                    :href="creation.sourceCodeUrl"
                    data-testid="github-link"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    {{ t('project.source_code') }}
                </BaseButton>
            </div>
        </div>
        <div class="relative hidden lg:block">
            <LightShape class="absolute top-0 -left-64 z-0" />
            <div
                class="bg-action-container-outer-color action-container-outer-shadow action-container-outer-border action-container-background-blur shrink-0 overflow-hidden rounded-3xl p-2"
            >
                <div
                    class="action-container-inner-shadow flex aspect-video flex-1 flex-col items-center justify-center overflow-hidden rounded-2xl border bg-white lg:h-[360px] 2xl:h-[480px]"
                >
                    <picture class="h-full w-full">
                        <source :srcset="creation.coverImage.webp.large" type="image/webp" />
                        <img
                            :src="creation.coverImage.avif.large"
                            :alt="t('project.project_cover_alt')"
                            class="h-full w-full object-cover"
                            loading="eager"
                        />
                    </picture>
                </div>
            </div>
        </div>
    </div>
</template>
