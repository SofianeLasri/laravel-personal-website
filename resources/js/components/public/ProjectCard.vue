<script setup lang="ts">
import { SSRCreation } from '@/types';

const props = defineProps<{
    creation: SSRCreation;
}>();

const startYear = new Date(props.creation.startedAt).getFullYear();
const endYear = props.creation.endedAt ? new Date(props.creation.endedAt).getFullYear() : null;

let formattedYears: string;

if (startYear && endYear) {
    formattedYears = `${startYear} - ${endYear}`;
} else {
    formattedYears = `${startYear} - Aujd`;
}

console.log(props.creation.coverImage);
</script>

<template>
    <div class="flex w-full flex-shrink-0 flex-col gap-4 select-none md:w-[40rem]">
        <div class="flex aspect-video flex-col gap-2.5 overflow-hidden rounded-2xl shadow-[0px_0.25rem_0.5rem_0px_rgba(0,0,0,0.25)]">
            <img class="h-full w-full object-cover" alt="Project Image" :src="creation.coverImage" draggable="false" />
        </div>
        <div class="flex gap-4 rounded-2xl">
            <div
                class="outline-border flex size-20 items-center justify-center gap-2.5 rounded-lg bg-white p-4 shadow-[0px_0.25rem_0.5rem_0px_rgba(0,0,0,0.25)] outline"
            >
                <img class="flex-1" :src="creation.logo" alt="Logo of the project" draggable="false" />
            </div>
            <div class="flex flex-1 flex-col gap-4">
                <div class="flex flex-col gap-px">
                    <div class="text-design-system-title justify-center text-base font-bold">
                        {{ creation.name }}
                    </div>
                    <div class="text-design-system-paragraph justify-center text-sm font-semibold" lang="fr">
                        {{ creation.type }}
                    </div>
                </div>
                <div class="text-design-system-paragraph justify-center text-sm font-normal">
                    {{ formattedYears }}
                </div>
            </div>
            <div class="flex flex-col gap-2.5 py-2">
                <div class="flex items-center gap-2">
                    <div v-for="technology in creation.technologies" :key="technology.name">
                        <div v-html="technology.svgIcon" class="size-4"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
