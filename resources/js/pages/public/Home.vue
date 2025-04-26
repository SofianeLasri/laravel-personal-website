<script setup lang="ts">
import ArrowUpRightRegular from '@/components/font-awesome/ArrowUpRightRegular.vue';
import PlusRegular from '@/components/font-awesome/PlusRegular.vue';
import LightShape from '@/components/public/LightShape.vue';
import Navbar from '@/components/public/Navbar.vue';
import ProjectCard from '@/components/public/ProjectCard.vue';
import ProjectSlider from '@/components/public/ProjectSlider.vue';
import TechnologyExperiencesContainer from '@/components/public/TechnologyExperiencesContainer.vue';
import BlackButton from '@/components/public/ui/BlackButton.vue';
import BlackLinkButton from '@/components/public/ui/BlackLinkButton.vue';
import LightLinkButton from '@/components/public/ui/LightLinkButton.vue';
import SectionParagraph from '@/components/public/ui/SectionParagraph.vue';
import SectionTitle from '@/components/public/ui/SectionTitle.vue';
import LaravelCertification from '@/components/shapes/LaravelCertification.vue';
import LaravelLogo from '@/components/shapes/LaravelLogo.vue';
import Cube from '@/components/shapes/cube.vue';
import { SocialMediaLink, SSRCreation, SSRTechnologyExperience } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{
    socialMediaLinks: SocialMediaLink[];
    yearsOfExperience: number;
    developmentCreationsCount: number;
    technologiesCount: number;
    laravelCreations: SSRCreation[];
    technologyExperiences: SSRTechnologyExperience[];
}>();
</script>

<template>
    <Head title="Accueil" />
    <div class="relative flex min-h-screen flex-col items-center overflow-x-hidden bg-[url(/resources/images/public/dots.svg)] px-2">
        <Navbar />

        <LightShape class="absolute top-0 left-[-27rem] z-0 xl:left-[-15rem]" />
        <div class="container inline-flex items-center py-16">
            <div class="relative inline-flex flex-1 flex-col items-start self-stretch">
                <h1 class="flex flex-1 flex-col items-start justify-center self-stretch sm:gap-1">
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
                                    {{ yearsOfExperience }}
                                </div>
                                <div class="text-design-system-paragraph self-stretch text-sm font-normal sm:text-base sm:leading-5">
                                    Années d’expériences
                                </div>
                                <PlusRegular class="fill-design-system-title absolute top-0 left-0 size-4" />
                            </div>
                            <div class="flex items-center self-stretch py-8">
                                <div class="bg-border w-px self-stretch" />
                            </div>
                            <div class="relative inline-flex w-24 flex-col items-center justify-center gap-1 rounded-2xl p-2 sm:w-32">
                                <div class="text-design-system-title justify-center self-stretch text-center text-4xl font-bold sm:text-6xl">
                                    {{ developmentCreationsCount }}
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
                                    {{ technologiesCount }}
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

        <LightShape class="absolute top-[40rem] right-[-27rem] z-0 xl:right-[-15rem]" />

        <div class="container mt-16 flex flex-col gap-32" id="backend-and-laravel-specialization-section">
            <section class="flex">
                <div class="hidden flex-1 items-center justify-center xl:flex">
                    <div class="h-64 w-60 overflow-hidden">
                        <LaravelLogo class="size-60" />
                    </div>
                    <Cube class="motion-preset-oscillate motion-duration-5000 absolute top-[-27px] left-[61px]" />
                    <Cube class="motion-preset-oscillate motion-duration-5000 absolute top-[341px] left-[586px]" />
                </div>
                <div class="flex flex-1 flex-col gap-8">
                    <div class="flex items-center gap-4">
                        <LaravelLogo class="size-12 xl:hidden" />
                        <SectionTitle>
                            <span>Spécialisé en back-end PHP & </span>
                            <span class="text-[#FF2D20]">Laravel.</span>
                        </SectionTitle>
                    </div>

                    <div>
                        <SectionParagraph>
                            Développant des projets en PHP depuis 2019, s’il y a un bien un domaine dans lequel j’excelle c’est bien celui du
                            développement d’application back-end (en PHP).
                        </SectionParagraph>
                        <SectionParagraph>
                            Cela ne signifie pas pour autant que je suis mauvais en front-end, les interfaces ont toujours eu une place importante au
                            sein de mes projets. :)
                        </SectionParagraph>
                        <SectionParagraph>
                            Mais ayant développé une sorte de micro framework PHP pendant près de 2 ans, avant d’être littéralement tombé amoureux de
                            Laravel, je pense pouvoir légitimement dire être spécialisé sur ces deux technos.
                        </SectionParagraph>
                    </div>
                </div>
            </section>

            <section class="flex flex-col gap-16" id="laravel-section">
                <div class="flex">
                    <div class="flex flex-1 flex-col gap-8">
                        <SectionTitle>Avec de nombreux projets à mon actif.</SectionTitle>
                        <SectionParagraph>
                            Sans avoir le niveau d’un développeur senior comme le prétend ma certification Laravel, je maitrise assez bien le
                            framework. Je vous invite à regarder les différents projets que j’ai réalisé avec, ils ont tous leur petite spécificité.
                            :)
                        </SectionParagraph>
                    </div>
                    <div class="hidden flex-1 xl:block"></div>
                </div>

                <div class="flex flex-col gap-8">
                    <ProjectSlider :items="laravelCreations">
                        <ProjectCard v-for="creation in laravelCreations" :key="creation.id" :creation="creation" />
                    </ProjectSlider>
                    <div>
                        <BlackButton>
                            <span>Voir mes autres projets</span>
                            <ArrowUpRightRegular class="h-4 fill-white" />
                        </BlackButton>
                    </div>
                </div>
            </section>

            <section class="flex flex-col items-center gap-16" id="other-skills-section">
                <div class="inline-flex max-w-[56rem] flex-col items-center gap-8 text-center">
                    <SectionTitle>Mais j'ai aussi d'autres compétences !</SectionTitle>
                    <SectionParagraph>
                        De manière générale, j’aime toucher à tout. Il est très important pour moi de savoir être autonome, quel que soit le domaine.
                    </SectionParagraph>
                </div>
                <TechnologyExperiencesContainer :experiences="technologyExperiences" />
            </section>
        </div>
    </div>
</template>
