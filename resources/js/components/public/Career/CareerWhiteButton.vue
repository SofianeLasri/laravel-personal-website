<script setup lang="ts">
import { SSRExperience } from '@/types';

defineProps<{
    experience: SSRExperience;
}>();

const emit = defineEmits(['click']);
</script>

<template>
    <div class="flex cursor-pointer items-start justify-start gap-4 self-stretch rounded-2xl bg-white p-2 shadow-lg" @click="emit('click')">
        <div class="outline-border flex size-20 items-center justify-center rounded-xl bg-white p-2 outline-1">
            <img class="h-full w-full object-contain" :src="experience.logo || 'https://placehold.co/38x38'" alt="Logo" />
        </div>
        <div class="flex flex-col content-between gap-4">
            <div class="flex flex-col items-start justify-start gap-px self-stretch">
                <div class="justify-center self-stretch font-bold text-black">{{ experience.title }}</div>
                <div class="justify-center self-stretch text-sm font-medium text-gray-800">
                    {{ experience.organizationName }}
                </div>
            </div>
            <div class="justify-center self-stretch text-sm font-medium text-gray-800">
                {{ new Date(experience.startedAt).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' }) }} -
                {{
                    experience.endedAt ? new Date(experience.endedAt).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' }) : "Aujourd'hui"
                }}
            </div>
        </div>
    </div>
</template>
