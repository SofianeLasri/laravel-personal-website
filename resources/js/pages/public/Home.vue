<script setup lang="ts">
import ArrowUpRightRegular from '@/components/font-awesome/ArrowUpRightRegular.vue';
import CareerContainer from '@/components/public/CareerContainer.vue';
import HeroSection from '@/components/public/HeroSection.vue';
import LightShape from '@/components/public/LightShape.vue';
import Navbar from '@/components/public/Navbar.vue';
import ProjectCard from '@/components/public/ProjectCard.vue';
import ProjectSlider from '@/components/public/ProjectSlider.vue';
import TechnologyExperiencesContainer from '@/components/public/TechnologyExperiencesContainer.vue';
import BlackButton from '@/components/public/ui/BlackButton.vue';
import SectionParagraph from '@/components/public/ui/SectionParagraph.vue';
import SectionTitle from '@/components/public/ui/SectionTitle.vue';
import LaravelLogo from '@/components/shapes/LaravelLogo.vue';
import { SocialMediaLink, SSRCreation, SSRExperience, SSRTechnologyExperience } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{
    socialMediaLinks: SocialMediaLink[];
    yearsOfExperience: number;
    developmentCreationsCount: number;
    technologiesCount: number;
    laravelCreations: SSRCreation[];
    technologyExperiences: SSRTechnologyExperience[];
    experiences: SSRExperience[];
}>();
</script>

<template>
    <Head title="Accueil" />
    <div class="relative flex min-h-screen flex-col items-center overflow-hidden bg-[url(/resources/images/public/dots.svg)] px-2">
        <Navbar />

        <LightShape class="absolute top-0 left-[-27rem] z-0 xl:left-[-15rem]" />
        <HeroSection
            :socialMediaLinks="socialMediaLinks"
            :yearsOfExperience="yearsOfExperience"
            :developmentCreationsCount="developmentCreationsCount"
            :technologiesCount="technologiesCount"
        />

        <LightShape class="absolute top-[40rem] right-[-27rem] z-0 xl:right-[-15rem]" />

        <div class="container mt-16 mb-16 flex flex-col gap-32" id="backend-and-laravel-specialization-section">
            <section class="flex">
                <div class="hidden flex-1 items-center justify-center xl:flex">
                    <div class="h-64 w-60 overflow-hidden">
                        <LaravelLogo class="size-60" />
                    </div>
                    <!--                    <Cube class="motion-preset-oscillate motion-duration-5000 absolute top-[-27px] left-[61px]" />
                    <Cube class="motion-preset-oscillate motion-duration-5000 absolute top-[341px] left-[586px]" />-->
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

            <section class="flex flex-col items-center gap-16" id="career-section">
                <div class="flex">
                    <div class="flex flex-1 flex-col gap-8">
                        <SectionTitle>Mon parcours professionnel & scolaire.</SectionTitle>
                        <SectionParagraph>
                            Bien souvent, c’est au travers de mes projets personnels que j’apprends le plus. Mais mon parcours scolaire et
                            professionnel m’a permis d’obtenir de solides bases dans plusieurs domaines.
                        </SectionParagraph>
                    </div>
                    <div class="hidden flex-1 xl:block"></div>
                </div>
                <CareerContainer :experience="experiences" />
            </section>
        </div>
    </div>
</template>
