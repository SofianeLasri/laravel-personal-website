<script setup lang="ts">
import TechnologyExperienceCard from '@/components/public/Technology/TechnologyExperienceCard.vue';
import BlackButton from '@/components/public/ui/BlackButton.vue';
import WhiteButton from '@/components/public/ui/WhiteButton.vue';
import { SSRTechnologyExperience } from '@/types';
import { computed, ref } from 'vue';

const props = defineProps<{
    experiences: SSRTechnologyExperience[];
}>();

const selectedType = ref<'framework' | 'library' | 'language' | 'other' | 'all'>('framework');

const filteredTechnologies = computed(() => {
    if (selectedType.value === 'all') {
        return props.experiences;
    }

    if (selectedType.value === 'framework' || selectedType.value === 'library') {
        return props.experiences.filter((tech) => tech.type === 'framework' || tech.type === 'library');
    }

    return props.experiences.filter((tech) => tech.type === selectedType.value);
});

const setTechType = (type: 'framework' | 'library' | 'language' | 'other' | 'all') => {
    selectedType.value = type;
};

const isButtonActive = (type: string): boolean => {
    if (type === 'frameworks-libraries') {
        return selectedType.value === 'framework' || selectedType.value === 'library';
    }
    return selectedType.value === type;
};
</script>

<template>
    <div class="flex gap-16 self-stretch">
        <div class="flex w-72 shrink-0 flex-col gap-4">
            <BlackButton v-if="isButtonActive('frameworks-libraries')" @click="setTechType('framework')"> Framework & Librairies </BlackButton>
            <WhiteButton v-else @click="setTechType('framework')"> Framework & Librairies </WhiteButton>

            <BlackButton v-if="isButtonActive('language')" @click="setTechType('language')"> Langages de programmation </BlackButton>
            <WhiteButton v-else @click="setTechType('language')"> Langages de programmation </WhiteButton>

            <BlackButton v-if="isButtonActive('other')" @click="setTechType('other')"> Annexes </BlackButton>
            <WhiteButton v-else @click="setTechType('other')"> Annexes </WhiteButton>
        </div>
        <div class="grid grow grid-cols-1 gap-8 lg:grid-cols-2">
            <TechnologyExperienceCard v-for="experience in filteredTechnologies" :key="experience.id" :experience="experience" />
        </div>
    </div>
</template>
