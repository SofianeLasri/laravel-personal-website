<script setup lang="ts">
import PlusRegular from '@/components/font-awesome/PlusRegular.vue';
import BlackLinkButton from '@/components/public/ui/BlackLinkButton.vue';
import LightLinkButton from '@/components/public/ui/LightLinkButton.vue';
import LaravelCertification from '@/components/shapes/LaravelCertification.vue';
import Cube from '@/components/shapes/cube.vue';
import { SocialMediaLink } from '@/types';
import { onMounted, ref } from 'vue';

const props = defineProps<{
    socialMediaLinks: SocialMediaLink[];
    yearsOfExperience: number;
    developmentCreationsCount: number;
    technologiesCount: number;
}>();

// Valeurs réactives pour l'animation des compteurs
const animatedYearsOfExperience = ref(0);
const animatedDevelopmentCreationsCount = ref(0);
const animatedTechnologiesCount = ref(0);

const animateCounter = (startValue: number, endValue: number, duration: number, updateFn: (value: number) => void) => {
    const startTime = performance.now();
    const updateCounter = (currentTime: number) => {
        const elapsedTime = currentTime - startTime;
        const progress = Math.min(elapsedTime / duration, 1);

        const easeProgress = 1 - (1 - progress) * (1 - progress);

        const currentValue = Math.floor(startValue + (endValue - startValue) * easeProgress);
        updateFn(currentValue);

        if (progress < 1) {
            requestAnimationFrame(updateCounter);
        }
    };

    requestAnimationFrame(updateCounter);
};

onMounted(() => {
    animateCounter(0, props.yearsOfExperience, 1500, (value) => {
        animatedYearsOfExperience.value = value;
    });

    animateCounter(0, props.developmentCreationsCount, 2000, (value) => {
        animatedDevelopmentCreationsCount.value = value;
    });

    animateCounter(0, props.technologiesCount, 2500, (value) => {
        animatedTechnologiesCount.value = value;
    });
});
</script>
<template>
    <div class="container inline-flex items-center py-16">
        <div class="relative inline-flex flex-1 flex-col items-start self-stretch">
            <h1
                class="motion-translate-x-in-[0%] motion-translate-y-in-[5%] motion-blur-in-[8px] motion-duration-[0.16s]/blur flex flex-1 flex-col items-start justify-center self-stretch sm:gap-1"
            >
                <span class="text-design-system-paragraph justify-center self-stretch text-xl font-medium sm:text-2xl sm:leading-6">
                    Hello, je suis
                </span>
                <span class="text-design-system-title justify-center self-stretch text-3xl font-semibold sm:text-5xl sm:leading-12">
                    Développeur
                </span>
                <span class="text-primary justify-center self-stretch text-6xl font-bold sm:text-8xl sm:leading-24">Full-Stack.</span>
            </h1>
            <div class="flex flex-col items-start gap-8 self-stretch">
                <div class="inline-flex items-center gap-2 py-12 xl:py-0">
                    <BlackLinkButton :href="route('cv')" title="CV" target="_blank"> Télécharger mon CV </BlackLinkButton>
                    <LightLinkButton v-for="link in socialMediaLinks" :key="link.name" :href="link.url" :title="link.name" target="_blank">
                        <div class="absolute flex h-4 fill-black" v-html="link.icon_svg"></div>
                    </LightLinkButton>
                </div>
                <div class="flex flex-wrap items-center gap-4 self-stretch">
                    <div class="size- flex items-start gap-4">
                        <div class="relative inline-flex w-24 flex-col items-center justify-center gap-1 rounded-2xl p-2 sm:w-32">
                            <div class="text-design-system-title justify-center self-stretch text-center text-4xl font-bold sm:text-6xl">
                                {{ animatedYearsOfExperience }}
                            </div>
                            <div class="text-design-system-paragraph self-stretch text-sm font-normal sm:text-base sm:leading-5">
                                Années d'expériences
                            </div>
                            <PlusRegular class="fill-design-system-title absolute top-0 left-0 size-4" />
                        </div>
                        <div class="flex items-center self-stretch py-8">
                            <div class="bg-border w-px self-stretch" />
                        </div>
                        <div class="relative inline-flex w-24 flex-col items-center justify-center gap-1 rounded-2xl p-2 sm:w-32">
                            <div class="text-design-system-title justify-center self-stretch text-center text-4xl font-bold sm:text-6xl">
                                {{ animatedDevelopmentCreationsCount }}
                            </div>
                            <div class="text-design-system-paragraph self-stretch text-sm font-normal sm:text-base sm:leading-5">
                                Projets réalisés
                            </div>
                            <PlusRegular class="fill-design-system-title absolute top-0 left-0 size-4" />
                        </div>
                        <div class="flex items-center self-stretch py-8">
                            <div class="bg-border w-px self-stretch" />
                        </div>
                        <div class="relative inline-flex w-24 flex-col items-center justify-center gap-1 rounded-2xl p-2 sm:w-32">
                            <div class="text-design-system-title justify-center self-stretch text-center text-4xl font-bold sm:text-6xl">
                                {{ animatedTechnologiesCount }}
                            </div>
                            <div class="text-design-system-paragraph self-stretch text-sm font-normal sm:text-base sm:leading-5">
                                Frameworks maîtrisés
                            </div>
                            <PlusRegular class="fill-design-system-title absolute top-0 left-0 size-4" />
                        </div>
                    </div>
                    <LaravelCertification class="h-40" />
                </div>
            </div>
            <Cube class="motion-preset-oscillate motion-duration-5000 absolute top-[67px] left-[496px]" />
            <Cube class="motion-preset-oscillate motion-duration-5000 absolute top-[540px] left-[805px] hidden xl:block" />
            <Cube class="motion-preset-oscillate motion-duration-5000 absolute top-[300px] left-[-80px]" />
        </div>
        <div class="bg-primary z-1 hidden size-[35rem] flex-shrink-0 items-end justify-end overflow-hidden rounded-2xl xl:flex">
            <img class="h-full" src="/resources/images/public/big-head-transparent.avif" alt="Photo de Sofiane Lasri" />
        </div>
    </div>
</template>
