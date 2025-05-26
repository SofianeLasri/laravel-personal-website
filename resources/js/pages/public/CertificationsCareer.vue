<script setup lang="ts">
import LightShape from '@/components/public/LightShape.vue';
import HeroSectionTitle from '@/components/public/Ui/HeroSectionTitle.vue';
import SectionParagraph from '@/components/public/Ui/SectionParagraph.vue';
import PublicAppLayout from '@/layouts/PublicAppLayout.vue';
import { SocialMediaLink } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{
    socialMediaLinks: SocialMediaLink[];
}>();
</script>

<template>
    <Head title="Certifications & Parcours" />
    <PublicAppLayout :socialMediaLinks="socialMediaLinks">
        <div class="absolute top-0 left-0 z-0 h-full w-full overflow-hidden">
            <LightShape class="absolute top-0 left-[-27rem] xl:left-[-15rem]" />
        </div>

        <div class="relative z-10 container mt-16 mb-16 px-4">
            <div class="mb-12 flex">
                <div class="flex flex-1 flex-col gap-6">
                    <HeroSectionTitle>Certifications & Parcours.</HeroSectionTitle>
                    <SectionParagraph> Retrouvez mes certifications et mon parcours (scolaire et professionnel). </SectionParagraph>
                </div>
                <div class="hidden flex-1 xl:block"></div>
            </div>

            <div class="flex flex-col items-start justify-start gap-16">
                <div class="flex w-full flex-col items-start justify-start gap-8">
                    <h3 class="text-design-system-title text-2xl font-bold">Certifications</h3>

                    <div class="grid w-full grid-cols-1 gap-16 sm:grid-cols-2 lg:grid-cols-3">
                        <a class="flex items-center justify-start gap-8" href="#">
                            <div class="flex items-center justify-center">
                                <picture>
                                    <source srcset="https://placehold.co/128x128" type="image/webp" />
                                    <img src="https://placehold.co/128x128" alt="Logo of the project" class="size-32" loading="lazy" />
                                </picture>
                            </div>

                            <div class="flex flex-col gap-2">
                                <div class="text-design-system-title text-xl font-bold">Certification for Laravel</div>
                                <div class="flex flex-col gap-2">
                                    <div class="text-design-system-paragraph justify-center text-sm font-semibold">Niveau : Senior Developer</div>
                                    <div class="text-design-system-paragraph justify-center text-sm font-semibold">Score : 88.9%</div>
                                    <div class="text-design-system-paragraph justify-center text-sm font-semibold">Date : 27 février 2025</div>
                                </div>
                            </div>
                        </a>

                        <a class="flex items-center justify-start gap-8" href="#">
                            <div class="flex items-center justify-center">
                                <picture>
                                    <source srcset="https://placehold.co/128x128" type="image/webp" />
                                    <img src="https://placehold.co/128x128" alt="Logo of the project" class="size-32" loading="lazy" />
                                </picture>
                            </div>

                            <div class="flex flex-col gap-2">
                                <div class="text-design-system-title text-xl font-bold">TOEIC</div>
                                <div class="flex flex-col gap-2">
                                    <div class="text-design-system-paragraph justify-center text-sm font-semibold">Niveau : B1</div>
                                    <div class="text-design-system-paragraph justify-center text-sm font-semibold">Score : 775/990</div>
                                    <div class="text-design-system-paragraph justify-center text-sm font-semibold">Date : 15 juin 2023</div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="flex flex-col gap-8">
                    <h3 class="text-design-system-title text-2xl font-bold">Parcours scolaire</h3>

                    <div class="grid w-full grid-cols-1 gap-16 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <a class="flex gap-4 rounded-2xl" href="#">
                            <div
                                class="flex size-20 flex-shrink-0 items-center justify-center gap-2.5 rounded-lg border bg-white p-3 shadow-[0px_0.25rem_0.5rem_0px_rgba(0,0,0,0.25)] md:p-4"
                            >
                                <picture>
                                    <source srcset="https://placehold.co/128x128" type="image/webp" />
                                    <img
                                        src="https://placehold.co/128x128"
                                        alt="Logo of the project"
                                        class="h-full w-full object-cover"
                                        loading="lazy"
                                    />
                                </picture>
                            </div>
                            <div class="flex flex-1 flex-col gap-4">
                                <div class="flex flex-col gap-px">
                                    <div class="text-design-system-title justify-center text-base font-bold">Mastère Dev Manager Full-Stack</div>
                                    <div class="text-design-system-paragraph justify-center text-sm font-semibold">Efrei Paris</div>
                                </div>
                                <div class="text-design-system-paragraph justify-center text-sm font-normal">Sept. 2023 - Sept. 2025</div>
                            </div>
                        </a>

                        <a class="flex gap-4 rounded-2xl" href="#">
                            <div
                                class="flex size-20 flex-shrink-0 items-center justify-center gap-2.5 rounded-lg border bg-white p-3 shadow-[0px_0.25rem_0.5rem_0px_rgba(0,0,0,0.25)] md:p-4"
                            >
                                <picture>
                                    <source srcset="https://placehold.co/128x128" type="image/webp" />
                                    <img
                                        src="https://placehold.co/128x128"
                                        alt="Logo of the project"
                                        class="h-full w-full object-cover"
                                        loading="lazy"
                                    />
                                </picture>
                            </div>
                            <div class="flex flex-1 flex-col gap-4">
                                <div class="flex flex-col gap-px">
                                    <div class="text-design-system-title justify-center text-base font-bold">Licence PRISM</div>
                                    <div class="text-design-system-paragraph justify-center text-sm font-semibold">
                                        Université Paris-Saclay, IUT d'Orsay
                                    </div>
                                </div>
                                <div class="text-design-system-paragraph justify-center text-sm font-normal">Sept. 2022 - Sept. 2023</div>
                            </div>
                        </a>
                    </div>
                </div>
                <div class="flex flex-col gap-8">
                    <h3 class="text-design-system-title text-2xl font-bold">Parcours professionnelle</h3>

                    <div class="grid w-full grid-cols-1 gap-16 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <a class="flex gap-4 rounded-2xl" href="#">
                            <div
                                class="flex size-20 flex-shrink-0 items-center justify-center gap-2.5 rounded-lg border bg-white p-3 shadow-[0px_0.25rem_0.5rem_0px_rgba(0,0,0,0.25)] md:p-4"
                            >
                                <picture>
                                    <source srcset="https://placehold.co/128x128" type="image/webp" />
                                    <img
                                        src="https://placehold.co/128x128"
                                        alt="Logo of the project"
                                        class="h-full w-full object-cover"
                                        loading="lazy"
                                    />
                                </picture>
                            </div>
                            <div class="flex flex-1 flex-col gap-4">
                                <div class="flex flex-col gap-px">
                                    <div class="text-design-system-title justify-center text-base font-bold">Développeur Full-Stack</div>
                                    <div class="text-design-system-paragraph justify-center text-sm font-semibold">Kang</div>
                                </div>
                                <div class="text-design-system-paragraph justify-center text-sm font-normal">Avril 2022 - Aujourd'hui</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </PublicAppLayout>
</template>

<style scoped></style>
