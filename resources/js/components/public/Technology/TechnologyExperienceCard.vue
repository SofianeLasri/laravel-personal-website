<script setup lang="ts">
import ArrowUpRightRegular from '@/components/font-awesome/ArrowUpRightRegular.vue';
import BlackButtonSm from '@/components/public/ui/BlackButtonSm.vue';
import { SSRTechnologyExperience } from '@/types';

const props = defineProps<{
    experience: SSRTechnologyExperience;
}>();

let projectsNumberText: string;
if (props.experience.creationCount > 1) {
    projectsNumberText = `${props.experience.creationCount} projets`;
} else {
    projectsNumberText = `${props.experience.creationCount} projet`;
}
</script>

<template>
    <div
        class="motion-translate-x-in-[0%] motion-translate-y-in-[5%] motion-blur-in-[.5rem] motion-duration-[0.16s]/blur flex flex-col items-center justify-start rounded-2xl bg-black shadow-[0px_0.25rem_0.5rem_0px_rgba(0,0,0,0.25)] xl:aspect-video"
    >
        <div class="flex shrink-0 items-center justify-end gap-2.5 self-stretch px-4 py-1">
            <div class="justify-center text-sm text-white">
                {{ experience.typeLabel }}
            </div>
        </div>
        <div class="flex grow flex-col items-start justify-between self-stretch rounded-2xl bg-white px-4 py-6">
            <div class="flex flex-col items-start justify-start gap-4">
                <div class="flex items-center justify-start gap-4">
                    <div class="outline-border flex size-12 items-center justify-center gap-2.5 rounded-lg outline-1">
                        <div class="flex size-7 items-center justify-center" v-html="experience.svgIcon"></div>
                    </div>
                    <div class="size- flex flex-col items-start justify-start">
                        <div class="text-design-system-title justify-center text-xl font-bold">
                            {{ experience.name }}
                        </div>
                        <div class="text-design-system-paragraph justify-center text-sm font-medium">
                            {{ projectsNumberText }}
                        </div>
                    </div>
                </div>
                <div class="text-design-system-paragraph">
                    {{ experience.description }}
                </div>
            </div>
            <BlackButtonSm>
                <span>Voir les projets</span>
                <ArrowUpRightRegular class="h-4 fill-white" />
            </BlackButtonSm>
        </div>
    </div>
</template>

<style scoped></style>
