<script setup lang="ts">
import ArrowUpRightRegular from '@/components/font-awesome/ArrowUpRightRegular.vue';
import BriefcaseSolid from '@/components/font-awesome/BriefcaseSolid.vue';
import GraduationCapSolid from '@/components/font-awesome/GraduationCapSolid.vue';
import LocationDotSolid from '@/components/font-awesome/LocationDotSolid.vue';
import CareerBlackButton from '@/components/public/Career/CareerBlackButton.vue';
import CareerWhiteButton from '@/components/public/Career/CareerWhiteButton.vue';
import TechnologyCard from '@/components/public/Technology/TechnologyCard.vue';
import BlackButton from '@/components/public/Ui/Button/BlackButton.vue';
import WhiteButton from '@/components/public/Ui/Button/WhiteButton.vue';
import WhiteLinkButtonSm from '@/components/public/Ui/Button/WhiteLinkButtonSm.vue';
import Cube from '@/components/shapes/cube.vue';
import { useTranslation } from '@/composables/useTranslation';
import { SSRExperience, SSRTechnology } from '@/types';
import { computed, onMounted, ref, watch } from 'vue';
import VueMarkdown from 'vue-markdown-render';

const props = defineProps<{
    experience: SSRExperience[];
}>();

const { t } = useTranslation();

const selectedType = ref<'emploi' | 'formation'>('formation');
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

const selectedExperienceYear = computed(() => {
    if (selectedExperience.value) {
        return new Date(selectedExperience.value.startedAt).getFullYear();
    }
    return null;
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

const formatPeriod = (startDateFormatted: string, endDateFormatted: string | null) => {
    if (!endDateFormatted) {
        return `${startDateFormatted} - ${t('career.today')}`;
    }
    return `${startDateFormatted} - ${endDateFormatted}`;
};

const changeType = (type: 'emploi' | 'formation') => {
    selectedType.value = type;
    selectedExperienceId.value = null;
};

const selectExperience = (experienceId: number) => {
    selectedExperienceId.value = experienceId;
};

const getTechnologies = (technologies: SSRTechnology[]) => {
    if (!technologies) return [];
    if (Array.isArray(technologies)) return technologies;
    return [technologies];
};
</script>

<template>
    <div class="relative inline-flex flex-col items-center justify-start gap-8 self-stretch">
        <div
            class="outline-border action-container-shadow action-container-outer-border action-container-background-blur flex flex-col gap-2 rounded-2xl p-2 sm:flex-row"
        >
            <BlackButton v-if="selectedType === 'formation'" class="rounded-lg">
                <GraduationCapSolid class="h-4 fill-white" />
                <span>{{ t('career.education') }}</span>
            </BlackButton>
            <WhiteButton v-else class="rounded-lg" @click="changeType('formation')">
                <GraduationCapSolid class="h-4 fill-black" />
                <span>{{ t('career.education') }}</span>
            </WhiteButton>

            <BlackButton v-if="selectedType === 'emploi'" class="rounded-lg">
                <BriefcaseSolid class="h-4 fill-white" />
                <span>{{ t('career.professional') }}</span>
            </BlackButton>
            <WhiteButton v-else class="rounded-lg" @click="changeType('emploi')">
                <BriefcaseSolid class="h-4 fill-black" />
                <span>{{ t('career.professional') }}</span>
            </WhiteButton>
        </div>

        <div class="flex flex-col items-start justify-start gap-8 self-stretch lg:flex-row">
            <div class="flex w-full shrink-0 flex-col items-start justify-start gap-8 lg:w-96">
                <div v-for="yearGroup in experiencesByYear" :key="yearGroup.year" class="flex flex-col items-start justify-start gap-4 self-stretch">
                    <div
                        class="justify-center self-stretch text-xl font-bold lg:text-2xl"
                        :class="{ 'text-design-system-paragraph': selectedExperienceYear && parseInt(yearGroup.year) < selectedExperienceYear }"
                    >
                        {{ yearGroup.year }}
                        <template
                            v-if="
                                yearGroup.experiences[0].endedAt &&
                                new Date(yearGroup.experiences[0].endedAt).getFullYear() !== parseInt(yearGroup.year)
                            "
                        >
                            - {{ yearGroup.experiences[0].endedAt ? new Date(yearGroup.experiences[0].endedAt).getFullYear() : t('career.today') }}
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

            <div
                v-if="selectedExperience"
                class="bg-action-container-outer-color action-container-outer-shadow action-container-outer-border action-container-background-blur inline-flex grow gap-2.5 self-stretch rounded-3xl p-2"
            >
                <div
                    class="outline-border action-container-inner-shadow inline-flex w-full flex-col items-start justify-start rounded-2xl bg-white outline-1"
                >
                    <div class="relative inline-flex flex-col items-start justify-between self-stretch px-4 py-4 lg:flex-row lg:px-8 lg:py-6">
                        <div
                            class="absolute top-1 right-1 -bottom-2 left-1 z-0 rounded-t-2xl bg-[url(/resources/images/public/shadowed-dots.svg)] bg-size-[.6rem]"
                        >
                            <div class="from-atomic-tangerine-50 h-[200%] w-full rounded-[50%] bg-radial to-white/0"></div>
                        </div>
                        <div class="z-10 flex grow flex-col items-start justify-start gap-4 lg:flex-row lg:items-center">
                            <div
                                class="outline-border flex size-16 items-center justify-center gap-2.5 rounded-xl bg-white p-3 outline-1 lg:size-24 lg:p-4"
                            >
                                <picture class="flex h-full w-full items-center justify-center" v-if="selectedExperience.logo">
                                    <source :srcset="selectedExperience.logo.webp.thumbnail" type="image/webp" />
                                    <img
                                        :src="selectedExperience.logo.avif.thumbnail"
                                        :alt="selectedExperience.title"
                                        class="object-contain"
                                        loading="lazy"
                                    />
                                </picture>
                            </div>
                            <div class="inline-flex h-auto flex-1 flex-col items-start justify-between gap-2 lg:gap-0 xl:h-24">
                                <div class="flex flex-col items-start justify-start gap-0.5 self-stretch">
                                    <div class="text-design-system-title text-xl font-bold lg:text-2xl">{{ selectedExperience.title }}</div>
                                    <div class="text-design-system-title text-sm lg:text-base">{{ selectedExperience.organizationName }}</div>
                                </div>
                                <WhiteLinkButtonSm v-if="selectedExperience.websiteUrl" :href="selectedExperience.websiteUrl" target="_blank">
                                    <span>{{ t('career.visit_website') }}</span>
                                    <ArrowUpRightRegular class="h-4 fill-black" />
                                </WhiteLinkButtonSm>
                            </div>
                        </div>
                        <div class="z-10 mt-4 inline-flex flex-col items-start justify-end gap-2 lg:mt-0 lg:items-end xl:shrink-0">
                            <div class="text-design-system-title text-right text-sm font-bold lg:text-base">
                                {{ formatPeriod(selectedExperience.startedAtFormatted, selectedExperience.endedAtFormatted) }}
                            </div>
                            <div class="flex items-center justify-start gap-2">
                                <LocationDotSolid class="size-4" />
                                <span class="text-design-system-title text-sm lg:text-base">{{ selectedExperience.location }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Content -->
                    <div
                        class="outline-border z-10 flex grow flex-col items-start justify-start gap-6 self-stretch rounded-2xl bg-white p-4 outline-1 lg:gap-8 lg:p-8"
                    >
                        <div class="flex flex-col gap-3 self-stretch lg:gap-4">
                            <h3 class="text-design-system-title text-xl font-bold lg:text-2xl">{{ t('career.description') }}</h3>
                            <vue-markdown class="markdown-view text-sm lg:text-base" :source="selectedExperience.fullDescription" />
                        </div>

                        <div v-if="selectedExperience.technologies" class="flex flex-col items-start justify-start gap-3 self-stretch lg:gap-4">
                            <h3 class="text-design-system-title text-xl font-bold lg:text-2xl">{{ t('career.technologies_used') }}</h3>

                            <div class="grid grid-cols-1 gap-3 self-stretch sm:grid-cols-2 lg:gap-4 xl:grid-cols-3">
                                <TechnologyCard
                                    v-for="tech in getTechnologies(selectedExperience.technologies)"
                                    :key="tech.name"
                                    :name="tech.name"
                                    :description="tech.description"
                                    :iconPicture="tech.iconPicture"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-else
                class="bg-action-container-outer-color action-container-outer-shadow action-container-outer-border action-container-background-blur inline-flex grow gap-2.5 self-stretch rounded-3xl p-2"
            >
                <div
                    class="outline-border action-container-inner-shadow flex w-full items-center justify-center rounded-2xl bg-white p-4 outline-1 lg:p-8"
                >
                    <p class="text-design-system-paragraph text-center text-base lg:text-lg">
                        {{ t('career.select_experience') }}
                    </p>
                </div>
            </div>
        </div>
        <Cube
            class="motion-preset-oscillate motion-duration-5000 absolute top-[23px] hidden md:left-[100px] md:block lg:left-[250px] xl:left-[370px]"
        />
    </div>
</template>
