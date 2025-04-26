<script setup lang="ts">
import { SSRTechnologyExperience } from '@/types';
import BlackButton from '@/components/public/ui/BlackButton.vue';
import WhiteButton from '@/components/public/ui/WhiteButton.vue';
import TechnologyExperienceCard from '@/components/public/TechnologyExperienceCard.vue';

const props = defineProps<{
    experiences: SSRTechnologyExperience[];
}>();
</script>

<template>
    <div class="flex gap-16 self-stretch">
        <div class="flex w-72 shrink-0 flex-col gap-4">
            <BlackButton>Framework & Librairies</BlackButton>
            <WhiteButton>Langages de programmation</WhiteButton>
            <WhiteButton>Annexes</WhiteButton>
        </div>
        <div class="grid grow grid-cols-1 gap-8 lg:grid-cols-2">
            <TechnologyExperienceCard v-for="experience in experiences" :key="experience.id" :experience="experience" />
        </div>
    </div>
</template>