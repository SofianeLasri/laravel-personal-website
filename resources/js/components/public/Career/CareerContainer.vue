<script setup lang="ts">
import ArrowUpRightRegular from '@/components/font-awesome/ArrowUpRightRegular.vue';
import BriefcaseSolid from '@/components/font-awesome/BriefcaseSolid.vue';
import GraduationCapSolid from '@/components/font-awesome/GraduationCapSolid.vue';
import LocationDotSolid from '@/components/font-awesome/LocationDotSolid.vue';
import CareerBlackButton from '@/components/public/Career/CareerBlackButton.vue';
import CareerWhiteButton from '@/components/public/Career/CareerWhiteButton.vue';
import BlackButton from '@/components/public/ui/BlackButton.vue';
import WhiteButton from '@/components/public/ui/WhiteButton.vue';
import WhiteLinkButtonSm from '@/components/public/ui/WhiteLinkButtonSm.vue';
import Cube from '@/components/shapes/cube.vue';
import { SSRExperience } from '@/types';
import { computed, onMounted, ref, watch } from 'vue';
import VueMarkdown from 'vue-markdown-render';

const props = defineProps<{
    experience: SSRExperience[];
}>();

console.log(props.experience);

const selectedType = ref<'emploi' | 'formation'>('emploi');
const selectedExperienceId = ref<number | null>(null);

const filteredExperiences = computed(() => {
    return props.experience.filter((exp) => {
        if (selectedType.value === 'emploi') {
            return exp.type === 'emploi';
        } else {
            return exp.type === 'formation';
        }
    });
});

const experiencesByYear = computed(() => {
    const groupedExperiences: Record<string, SSRExperience[]> = {};

    filteredExperiences.value.forEach((exp) => {
        const year = new Date(exp.startedAt).getFullYear().toString();
        if (!groupedExperiences[year]) {
            groupedExperiences[year] = [];
        }
        groupedExperiences[year].push(exp);
    });

    return Object.entries(groupedExperiences)
        .sort(([a], [b]) => parseInt(b) - parseInt(a))
        .map(([year, exps]) => ({
            year,
            experiences: exps.sort((a, b) => new Date(b.startedAt).getTime() - new Date(a.startedAt).getTime()),
        }));
});

const selectedExperience = computed(() => {
    return props.experience.find((exp) => exp.id === selectedExperienceId.value) || null;
});

const selectFirstAvailableExperience = () => {
    if (experiencesByYear.value.length > 0 && (!selectedExperienceId.value || !selectedExperience.value)) {
        selectedExperienceId.value = experiencesByYear.value[0].experiences[0].id;
    }
};

watch(
    [() => experiencesByYear.value, selectedType],
    () => {
        selectFirstAvailableExperience();
    },
    { immediate: true },
);

onMounted(() => {
    selectFirstAvailableExperience();
});

const formatPeriod = (startDate: string, endDate: string | null) => {
    const start = new Date(startDate);
    const startMonth = start.toLocaleString('fr-FR', { month: 'long' });
    const startYear = start.getFullYear();

    if (!endDate) {
        return `${startMonth} ${startYear} - Aujourd'hui`;
    }

    const end = new Date(endDate);
    const endMonth = end.toLocaleString('fr-FR', { month: 'long' });
    const endYear = end.getFullYear();

    return `${startMonth} ${startYear} - ${endMonth} ${endYear}`;
};

const changeType = (type: 'emploi' | 'formation') => {
    selectedType.value = type;
    selectedExperienceId.value = null;
};

const selectExperience = (experienceId: number) => {
    selectedExperienceId.value = experienceId;
};

const getTechnologies = (technologies: any) => {
    if (!technologies) return [];
    if (Array.isArray(technologies)) return technologies;
    return [technologies];
};

const defaultSvgIcon =
    '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.5 2 2 6.5 2 12C2 17.5 6.5 22 12 22C17.5 22 22 17.5 22 12C22 6.5 17.5 2 12 2ZM12 20C7.59 20 4 16.41 4 12C4 7.59 7.59 4 12 4C16.41 4 20 7.59 20 12C20 16.41 16.41 20 12 20ZM12.5 7H11V13L16.2 16.2L17 14.9L12.5 12.2V7Z" fill="currentColor"></path></svg>';
</script>

<template>
    <div class="relative inline-flex flex-col items-center justify-start gap-8 self-stretch">
        <!-- Switch -->
        <div class="outline-border action-container-shadow action-container-outer-border action-container-background-blur flex gap-2 rounded-2xl p-2">
            <BlackButton v-if="selectedType === 'emploi'" class="rounded-lg">
                <BriefcaseSolid class="h-4 fill-white" />
                <span>Professionnel</span>
            </BlackButton>
            <WhiteButton v-else class="rounded-lg" @click="changeType('emploi')">
                <BriefcaseSolid class="h-4 fill-black" />
                <span>Professionnel</span>
            </WhiteButton>

            <BlackButton v-if="selectedType === 'formation'" class="rounded-lg">
                <GraduationCapSolid class="h-4 fill-white" />
                <span>Éducation</span>
            </BlackButton>
            <WhiteButton v-else class="rounded-lg" @click="changeType('formation')">
                <GraduationCapSolid class="h-4 fill-black" />
                <span>Éducation</span>
            </WhiteButton>
        </div>

        <div class="flex items-start justify-start gap-8 self-stretch">
            <div class="flex w-96 shrink-0 flex-col items-start justify-start gap-8">
                <!-- Liste des expériences par année -->
                <div v-for="yearGroup in experiencesByYear" :key="yearGroup.year" class="flex flex-col items-start justify-start gap-4 self-stretch">
                    <div class="justify-center self-stretch text-2xl font-bold">
                        {{ yearGroup.year }}
                        <template
                            v-if="
                                yearGroup.experiences[0].endedAt &&
                                new Date(yearGroup.experiences[0].endedAt).getFullYear() !== parseInt(yearGroup.year)
                            "
                        >
                            - {{ yearGroup.experiences[0].endedAt ? new Date(yearGroup.experiences[0].endedAt).getFullYear() : "Aujourd'hui" }}
                        </template>
                    </div>

                    <div class="flex flex-col items-start justify-start gap-3 self-stretch">
                        <template v-for="experience in yearGroup.experiences" :key="experience.id">
                            <CareerBlackButton
                                v-if="selectedExperienceId === experience.id"
                                :experience="experience"
                                @click="selectExperience(experience.id)"
                            />
                            <CareerWhiteButton v-else :experience="experience" @click="selectExperience(experience.id)" />
                        </template>
                    </div>
                </div>
            </div>

            <!-- Détails de l'expérience sélectionnée -->
            <div
                v-if="selectedExperience"
                class="bg-action-container-outer-color action-container-outer-shadow action-container-outer-border action-container-background-blur inline-flex grow gap-2.5 self-stretch rounded-3xl p-2"
            >
                <div
                    class="outline-border action-container-inner-shadow inline-flex w-full flex-col items-start justify-start rounded-2xl bg-white outline-1"
                >
                    <!-- Header -->
                    <div class="relative inline-flex items-start justify-between self-stretch px-8 py-6">
                        <div
                            class="absolute top-1 right-1 -bottom-2 left-1 z-0 rounded-t-2xl bg-[url(/resources/images/public/shadowed-dots.svg)] bg-size-[.6rem]"
                        >
                            <div class="from-atomic-tangerine-50 h-[200%] w-full rounded-[50%] bg-radial to-white/0"></div>
                        </div>
                        <div class="z-10 flex flex-1 items-center justify-start gap-4">
                            <div class="outline-border flex size-24 items-center justify-center gap-2.5 rounded-xl bg-white p-4 outline-1">
                                <img class="object-contain" :src="selectedExperience.logo || 'https://placehold.co/64x64'" alt="Logo" />
                            </div>
                            <div class="inline-flex h-24 flex-1 flex-col items-start justify-between">
                                <div class="flex flex-col items-start justify-start gap-0.5 self-stretch">
                                    <div class="text-design-system-title text-2xl font-bold">{{ selectedExperience.title }}</div>
                                    <div class="text-design-system-title">{{ selectedExperience.organizationName }}</div>
                                </div>
                                <WhiteLinkButtonSm v-if="selectedExperience.websiteUrl" :href="selectedExperience.websiteUrl" target="_blank">
                                    <span>Voir le site internet</span>
                                    <ArrowUpRightRegular class="h-4 fill-black" />
                                </WhiteLinkButtonSm>
                            </div>
                        </div>
                        <div class="z-10 inline-flex flex-col items-end justify-start gap-2">
                            <div class="text-design-system-title">
                                {{ formatPeriod(selectedExperience.startedAt, selectedExperience.endedAt) }}
                            </div>
                            <div class="flex items-center justify-start gap-2">
                                <LocationDotSolid class="size-4" />
                                <span class="text-design-system-title text-bold">{{ selectedExperience.location }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Content -->
                    <div
                        class="outline-border z-10 flex grow flex-col items-start justify-start gap-8 self-stretch rounded-2xl bg-white p-8 outline-1"
                    >
                        <div class="flex flex-col gap-4 self-stretch">
                            <h3 class="text-design-system-title text-2xl font-bold">Description</h3>
                            <vue-markdown class="markdown-view" :source="selectedExperience.fullDescription" />
                        </div>

                        <div v-if="selectedExperience.technologies" class="flex flex-col items-start justify-start gap-4 self-stretch">
                            <h3 class="text-design-system-title text-2xl font-bold">Technologies utilisées</h3>

                            <div class="grid grid-cols-2 gap-4 self-stretch lg:grid-cols-3">
                                <div
                                    v-for="tech in getTechnologies(selectedExperience.technologies)"
                                    :key="tech.name"
                                    class="flex items-center justify-start gap-2 rounded-lg p-2 outline-1 outline-gray-200"
                                >
                                    <div class="size-16" v-html="tech.svgIcon || defaultSvgIcon"></div>
                                    <div class="flex w-full flex-col justify-center gap-1">
                                        <div class="text-design-system-title">{{ tech.name }}</div>
                                        <div class="text-design-system-paragraph w-full justify-center text-sm">
                                            {{ tech.description }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Message si aucune expérience n'est sélectionnée -->
            <div
                v-else
                class="bg-action-container-outer-color action-container-outer-shadow action-container-outer-border action-container-background-blur inline-flex grow gap-2.5 self-stretch rounded-3xl p-2"
            >
                <div class="outline-border action-container-inner-shadow flex w-full items-center justify-center rounded-2xl bg-white p-8 outline-1">
                    <p class="text-center text-lg text-gray-500">Veuillez sélectionner une expérience pour voir les détails</p>
                </div>
            </div>
        </div>
        <Cube class="motion-preset-oscillate motion-duration-5000 absolute top-[23px] left-[370px]" />
    </div>
</template>
