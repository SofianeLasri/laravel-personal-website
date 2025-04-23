<script setup lang="ts">
import Dribble from '@/components/font-awesome/Dribble.vue';
import GitHub from '@/components/font-awesome/GitHub.vue';
import LinkedIn from '@/components/font-awesome/LinkedIn.vue';
import Plus from '@/components/font-awesome/Plus.vue';
import Background from '@/components/public/Background.vue';
import BlackButton from '@/components/public/BlackButton.vue';
import LightButton from '@/components/public/LightButton.vue';
import Navbar from '@/components/public/Navbar.vue';
import LaravelCertification from '@/components/shapes/LaravelCertification.vue';
import Cube from '@/components/shapes/cube.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Accueil" />
    <div class="flex min-h-screen flex-col items-center px-2">
        <Background />
        <Navbar />

        <div class="container inline-flex items-center justify-start py-16">
            <div class="relative inline-flex flex-1 flex-col items-start justify-start self-stretch">
                <div class="flex flex-1 flex-col items-start justify-center self-stretch sm:gap-1">
                    <div class="text-design-system-paragraph justify-center self-stretch text-xl font-medium sm:text-2xl sm:leading-6">
                        Hello, je suis
                    </div>
                    <div class="text-design-system-title justify-center self-stretch text-3xl font-semibold sm:text-5xl sm:leading-12">
                        Développeur
                    </div>
                    <div class="text-primary justify-center self-stretch text-6xl font-bold sm:text-8xl sm:leading-24">Full-Stack.</div>
                </div>
                <div class="flex flex-col items-start justify-start gap-8 self-stretch">
                    <div class="inline-flex items-center justify-start gap-2 py-12 xl:py-0">
                        <BlackButton>Télécharger mon CV</BlackButton>
                        <LightButton>
                            <LinkedIn class="absolute h-4 fill-black" />
                        </LightButton>
                        <LightButton>
                            <GitHub class="absolute h-4 fill-black" />
                        </LightButton>
                        <LightButton>
                            <Dribble class="absolute h-4 fill-black" />
                        </LightButton>
                    </div>
                    <div class="flex flex-wrap items-center justify-start gap-4 self-stretch">
                        <div class="size- flex items-start justify-start gap-4">
                            <div class="relative inline-flex w-24 flex-col items-center justify-center gap-1 rounded-2xl p-2 sm:w-32">
                                <div class="text-design-system-title justify-center self-stretch text-center text-4xl font-bold sm:text-6xl">2</div>
                                <div class="text-design-system-paragraph justify-start self-stretch text-sm font-normal sm:text-base sm:leading-5">
                                    Années d’expériences
                                </div>
                                <Plus class="fill-design-system-title absolute top-0 left-0 size-4" />
                            </div>
                            <div class="flex items-center self-stretch py-8">
                                <div class="bg-border w-px self-stretch" />
                            </div>
                            <div class="relative inline-flex w-24 flex-col items-center justify-center gap-1 rounded-2xl p-2 sm:w-32">
                                <div class="text-design-system-title justify-center self-stretch text-center text-4xl font-bold sm:text-6xl">10</div>
                                <div class="text-design-system-paragraph sm:leading justify-start self-stretch text-sm font-normal sm:text-base">
                                    Projets réalisés
                                </div>
                                <Plus class="fill-design-system-title absolute top-0 left-0 size-4" />
                            </div>
                            <div class="flex items-center self-stretch py-8">
                                <div class="bg-border w-px self-stretch" />
                            </div>
                            <div class="relative inline-flex w-24 flex-col items-center justify-center gap-1 rounded-2xl p-2 sm:w-32">
                                <div class="text-design-system-title justify-center self-stretch text-center text-4xl font-bold sm:text-6xl">5</div>
                                <div class="text-design-system-paragraph sm:leading justify-start self-stretch text-sm font-normal sm:text-base">
                                    Frameworks maîtrisés
                                </div>
                                <Plus class="fill-design-system-title absolute top-0 left-0 size-4" />
                            </div>
                        </div>
                        <LaravelCertification class="h-44 w-36" />
                    </div>
                </div>
                <Cube class="motion-preset-oscillate motion-duration-5000 absolute top-[67px] left-[496px] h-10" />
                <Cube class="motion-preset-oscillate motion-duration-5000 absolute top-[540px] left-[805px] h-10" />
                <Cube class="motion-preset-oscillate motion-duration-5000 absolute top-[300px] left-[-80px] h-10" />
            </div>
            <div class="bg-primary z-1 hidden size-[35rem] flex-shrink-0 items-end justify-end overflow-hidden rounded-2xl xl:flex">
                <img class="h-full" src="/resources/images/public/big-head-transparent.png" alt="Photo de Sofiane Lasri" />
            </div>
        </div>
    </div>
</template>
