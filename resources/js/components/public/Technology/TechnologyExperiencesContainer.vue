<script setup lang="ts">
import TechnologyExperienceCard from '@/components/public/Technology/TechnologyExperienceCard.vue';
import BaseButton from '@/components/public/Ui/Button/BaseButton.vue';
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
    <div class="flex flex-col gap-16 self-stretch 2xl:flex-row">
        <div class="flex shrink-0 flex-col justify-center gap-4 lg:flex-row 2xl:w-72 2xl:flex-col 2xl:justify-start">
            <BaseButton :variant="isButtonActive('frameworks-libraries') ? 'black' : 'white'" @click="setTechType('framework')">
                Framework & Librairies
            </BaseButton>

            <BaseButton :variant="isButtonActive('language') ? 'black' : 'white'" @click="setTechType('language')">
                Langages de programmation
            </BaseButton>

            <BaseButton :variant="isButtonActive('other') ? 'black' : 'white'" @click="setTechType('other')"> Annexes </BaseButton>
        </div>
        <div class="grid grow grid-cols-1 gap-8 xl:grid-cols-2">
            <TechnologyExperienceCard v-for="experience in filteredTechnologies" :key="experience.id" :experience="experience" />
        </div>
    </div>
</template>
