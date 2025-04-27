<script setup lang="ts">
import ArrowUpRightRegular from '@/components/font-awesome/ArrowUpRightRegular.vue';
import BriefcaseSolid from '@/components/font-awesome/BriefcaseSolid.vue';
import GraduationCapSolid from '@/components/font-awesome/GraduationCapSolid.vue';
import LocationDotSolid from '@/components/font-awesome/LocationDotSolid.vue';
import CareerBlackButton from '@/components/public/CareerBlackButton.vue';
import CareerWhiteButton from '@/components/public/CareerWhiteButton.vue';
import BlackButton from '@/components/public/ui/BlackButton.vue';
import WhiteButton from '@/components/public/ui/WhiteButton.vue';
import WhiteLinkButtonSm from '@/components/public/ui/WhiteLinkButtonSm.vue';
import Cube from '@/components/shapes/cube.vue';
</script>

<template>
    <div class="relative inline-flex flex-col items-center justify-start gap-8 self-stretch">
        <!-- Switch -->
        <div class="outline-border action-container-shadow action-container-outer-border action-container-background-blur flex gap-2 rounded-2xl p-2">
            <BlackButton class="rounded-lg">
                <BriefcaseSolid class="h-4 fill-white" />
                <span>Professionnel</span>
            </BlackButton>
            <WhiteButton class="rounded-lg">
                <GraduationCapSolid class="h-4 fill-black" />
                <span>Éducation</span>
            </WhiteButton>
        </div>

        <div class="flex items-start justify-start gap-8 self-stretch">
            <div class="flex w-96 shrink-0 flex-col items-start justify-start gap-8">
                <div class="flex flex-col items-start justify-start gap-4 self-stretch">
                    <div class="justify-center self-stretch text-2xl font-bold">2022 - 2025</div>
                    <div class="flex flex-col items-start justify-start gap-3 self-stretch">
                        <CareerBlackButton />
                        <CareerWhiteButton />
                    </div>
                </div>

                <div class="flex flex-col items-start justify-start gap-4 self-stretch">
                    <div class="text-design-system-paragraph justify-center self-stretch text-2xl font-bold">2021</div>
                    <div class="flex flex-col items-start justify-start gap-3 self-stretch">
                        <CareerWhiteButton />
                    </div>
                </div>
            </div>

            <div
                class="bg-action-container-outer-color action-container-outer-shadow action-container-outer-border action-container-background-blur inline-flex grow gap-2.5 self-stretch rounded-3xl p-2"
            >
                <div
                    class="outline-border action-container-inner-shadow inline-flex flex-col items-start justify-start rounded-2xl bg-white outline-1"
                >
                    <div class="relative inline-flex items-start justify-between self-stretch px-8 py-6">
                        <div
                            class="absolute top-1 right-1 -bottom-2 left-1 z-0 rounded-t-2xl bg-[url(/resources/images/public/shadowed-dots.svg)] bg-size-[.6rem]"
                        >
                            <div class="from-atomic-tangerine-50 h-[200%] w-full rounded-[50%] bg-radial to-white/0"></div>
                        </div>
                        <div class="z-10 flex flex-1 items-center justify-start gap-4">
                            <div class="outline-border flex size-24 items-center justify-center gap-2.5 rounded-xl bg-white p-4 outline-1">
                                <img class="flex-1 self-stretch" src="https://placehold.co/64x64" />
                            </div>
                            <div class="inline-flex h-24 flex-1 flex-col items-start justify-between">
                                <div class="flex flex-col items-start justify-start gap-0.5 self-stretch">
                                    <div class="text-design-system-title text-2xl font-bold">Développeur Full-Stack</div>
                                    <div class="text-design-system-title">Kang · Contrat en alternance</div>
                                </div>
                                <WhiteLinkButtonSm href="https://kang.fr/" target="_blank">
                                    <span>Voir le site internet</span>
                                    <ArrowUpRightRegular class="h-4 fill-black" />
                                </WhiteLinkButtonSm>
                            </div>
                        </div>
                        <div class="z-10 inline-flex w-48 flex-col items-end justify-start gap-2">
                            <div class="text-design-system-title">Avril 2022 - Aujourd’hui</div>
                            <div class="flex items-center justify-start gap-2">
                                <LocationDotSolid class="size-4" />
                                <span class="text-design-system-title">Paris</span>
                            </div>
                        </div>
                    </div>
                    <div class="outline-border z-10 flex flex-col items-start justify-start gap-8 self-stretch rounded-2xl bg-white p-8 outline-1">
                        <div class="flex flex-col items-start justify-start gap-4 self-stretch">
                            <div class="justify-center self-stretch text-2xl font-bold">Description</div>
                            <div class="text-design-system-paragraph justify-start self-stretch">
                                Lorem ipsum odor amet, consectetuer adipiscing elit. Metus egestas primis scelerisque, eget semper non amet metus.
                                Lectus magna potenti, elit montes convallis odio auctor sem. Dis sociosqu aliquet inceptos molestie purus at.
                                Malesuada ultricies scelerisque tempor mattis et dis rutrum tempus mauris. Auctor hendrerit sem fermentum parturient
                                lacus fringilla aenean. Sollicitudin eu scelerisque maecenas ridiculus praesent. Aptent elementum dolor felis blandit
                                magna nisl.
                                <br />
                                <br />Suspendisse quis semper natoque litora felis. Lobortis cubilia at interdum facilisis sit urna commodo diam. Amet
                                efficitur sagittis donec tempus; semper cras quisque dignissim. Eu potenti est consectetur mus sem arcu dapibus
                                vestibulum. Maecenas odio nibh porttitor elementum libero risus. Libero dignissim euismod ultricies parturient
                                conubia. Erat nunc ante tempor hac nibh justo pretium penatibus. Vulputate magnis morbi interdum nibh vulputate neque
                                aliquet et dictum. Finibus duis nascetur; placerat faucibus curae sapien leo non.
                                <br />
                                <br />Aliquet tempor taciti dapibus aliquam nisl lobortis ut hendrerit. Sagittis varius urna ut volutpat volutpat dis
                                quisque. Vehicula quis gravida mi ridiculus commodo. Nec vel nec ante condimentum dolor dui felis. Ridiculus laoreet
                                mollis ridiculus cras fusce ultrices. Auctor sapien faucibus nibh nibh fusce; adipiscing inceptos habitasse. Porttitor
                                ornare donec magnis odio sit lacinia placerat pretium. Ligula himenaeos risus ac mauris; nibh et tempor. Porta
                                habitant tempor etiam; vel hac diam.
                            </div>
                        </div>
                        <div class="flex w-[892px] flex-col items-start justify-start gap-4">
                            <div class="justify-center self-stretch text-2xl font-bold">Missions</div>
                            <div class="justify-start self-stretch">
                                <span class="text-design-system-paragraph">Modernisation et migration d'anciens projets :<br /></span
                                ><span class="text-design-system-paragraph">Portage et recréation sur Laravel<br /></span
                                ><span class="text-design-system-paragraph"
                                    >Analyse des besoins clients et fonctionnels<br />Adaptation et intégration des fonctionnalités nouvellement
                                    portées à l'écosystème existant (migration continue)<br />Création quasi systématique de tests unitaires (excepté
                                    pour les interfaces les plus simples)<br /></span
                                ><span class="text-design-system-paragraph">Design de nouvelles interfaces sur Figma<br /></span
                                ><span class="text-design-system-paragraph"
                                    >Respect de la charte graphique de l'entreprise<br />Usage de notre librairie graphique basée sur Bootstrap<br /></span
                                ><span class="text-design-system-paragraph">Optimisation des performances (dans la mesure du possible)<br /></span
                                ><span class="text-design-system-paragraph"
                                    >Simplification des requêtes SQL<br />Simplification générale du code<br />Usage du cache Redis</span
                                >
                            </div>
                        </div>
                        <div class="flex w-[892px] flex-col items-start justify-start gap-4">
                            <div class="justify-center self-stretch text-2xl font-bold">Technologies utilisées</div>
                            <div class="size- inline-flex items-start justify-start gap-4">
                                <div class="size- flex items-center justify-start gap-2 rounded-lg p-2 outline-1 outline-gray-200">
                                    <div class="flex size-16 items-center justify-center gap-2.5 overflow-hidden">
                                        <div class="size-8 bg-red-600"></div>
                                    </div>
                                    <div class="size- inline-flex flex-col items-start justify-center gap-1">
                                        <div class="justify-center">Laravel</div>
                                        <div class="text-design-system-paragraph w-48 justify-center text-sm">
                                            Framework principal, utilisé comme base pour tous les projets web.
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center justify-start gap-2 self-stretch rounded-lg p-2 outline-1 outline-gray-200">
                                    <div class="flex size-16 items-center justify-center gap-2.5 overflow-hidden">
                                        <div class="relative h-7 w-8 overflow-hidden">
                                            <div class="absolute top-0 left-0 h-7 w-8 bg-emerald-400"></div>
                                            <div class="absolute top-0 left-[6.40px] h-4 w-5 bg-slate-700"></div>
                                        </div>
                                    </div>
                                    <div class="inline-flex size-16 flex-col items-start justify-center gap-1">
                                        <div class="justify-center">Vue.js</div>
                                        <div class="text-design-system-paragraph w-48 justify-center text-sm">
                                            Framework front utilisé sur le site client.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <Cube class="motion-preset-oscillate motion-duration-5000 absolute top-[23px] left-[370px]" />
    </div>
</template>

<style scoped></style>
