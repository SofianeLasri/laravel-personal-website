<script setup lang="ts">
import LightShape from '@/components/public/LightShape.vue';
import HeroSectionTitle from '@/components/public/Ui/HeroSectionTitle.vue';
import SectionParagraph from '@/components/public/Ui/SectionParagraph.vue';
import { useTranslation } from '@/composables/useTranslation';
import PublicAppLayout from '@/layouts/PublicAppLayout.vue';
import { SocialMediaLink } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';

const page = usePage();
const { t } = useTranslation();

defineProps<{
    socialMediaLinks: SocialMediaLink[];
}>();

const pageTitle = t('about.title') + ' - Sofiane Lasri';
const pageDescription = t('about.subtitle');
const pageKeywords = 'Sofiane Lasri, développeur web, à propos, profil, portfolio, Laravel, Vue.js, Full Stack';
const pageUrl = page.props.ziggy.location;
const pageImage = '/opengraph-image-1200-630.jpg';
</script>

<template>
    <Head :title="pageTitle">
        <!-- SEO Meta Tags -->
        <meta name="description" :content="pageDescription" />
        <meta name="keywords" :content="pageKeywords" />
        <meta name="robots" content="index, follow" />

        <!-- Open Graph -->
        <meta property="og:type" content="website" />
        <meta property="og:title" :content="pageTitle" />
        <meta property="og:description" :content="pageDescription" />
        <meta property="og:url" :content="pageUrl" />
        <meta property="og:image" :content="pageImage" />
        <meta property="og:video:width" content="1200" />
        <meta property="og:video:height" content="630" />

        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" :content="pageTitle" />
        <meta name="twitter:description" :content="pageDescription" />
        <meta name="twitter:image" :content="pageImage" />
    </Head>
    <PublicAppLayout :socialMediaLinks="socialMediaLinks">
        <div class="absolute top-0 left-0 z-0 h-full w-full overflow-hidden">
            <LightShape class="absolute top-0 left-[-27rem] xl:left-[-15rem]" />
            <LightShape class="absolute top-[40rem] right-[-27rem] xl:right-[-15rem]" />
            <LightShape class="absolute top-[80rem] left-[-27rem] xl:left-[-15rem]" />
        </div>

        <div class="relative z-10 container px-4 pt-16 pb-8">
            <div class="mb-12 flex">
                <div class="flex flex-col gap-6">
                    <HeroSectionTitle>{{ t('about.title') }}</HeroSectionTitle>
                    <SectionParagraph>
                        {{ t('about.subtitle') }}
                    </SectionParagraph>
                </div>
                <div class="hidden xl:block"></div>
            </div>
        </div>

        <div class="relative z-10 container flex flex-col gap-16 px-4 pb-16" lang="fr-FR">
            <div class="flex flex-col gap-8">
                <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <div class="bg-primary z-1 flex aspect-square w-full flex-shrink-0 items-end justify-end overflow-hidden rounded-2xl">
                        <img class="h-full" src="/resources/images/public/big-head-geneva-may-2025.avif" alt="Photo de Sofiane Lasri" />
                    </div>
                    <div class="flex flex-col gap-8">
                        <div class="flex flex-col gap-4">
                            <div class="text-design-system-title text-xl font-bold">Qui suis-je ?</div>
                            <p class="text-design-system-paragraph">
                                Je m’appelle Sofiane LASRI-TRIENPONT et je suis développeur Full-Stack, spécialisé dans le développement
                                d’applications web basées sur le framework Laravel.<br /><br />Certains me connaissent peut-être au travers de mon
                                pseudo Steam “Cornflakes” pour les maps que j’ai réalisé sur Source Engine (Garry’s Mod).
                            </p>
                        </div>
                        <div class="flex flex-col gap-4">
                            <div class="text-design-system-title text-xl font-bold">Quels sont mes centres d’intérêt ?</div>
                            <p class="text-design-system-paragraph">
                                J’aime beaucoup concevoir et réaliser des projets, quel que soit le domaine tant que ça touche à l’informatique.
                                Toutefois, je porte une attention particulière aux projets liés aux jeux-vidéos.<br /><br />Hormis cela, j’aime
                                voyager afin de découvrir de nouveaux paysages, de nouveaux lieux. J’aime également la photographie. D’ailleurs, je
                                prends énormément de photos. Si le temps ne peut s’arrêter, la photo, elle, fige le moment présent.
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-col gap-8">
                        <div class="flex flex-col gap-4">
                            <div class="text-design-system-title text-xl font-bold">Développeur Full-Stack ?</div>
                            <p class="text-design-system-paragraph">
                                C’est vrai que c’est un terme à la mode depuis ces dernières années. Etymologiquement parlant, un développeur
                                Full-Stack est un développeur qui maîtrise l’ensemble de la stack technique.<br /><br />Concrètement, ça se traduit
                                par une aisance aussi bien dans le développement backend que frontend.
                            </p>
                        </div>
                        <div class="flex flex-col gap-4">
                            <div class="text-design-system-title text-xl font-bold">Suis-je limité à Laravel ?</div>
                            <p class="text-design-system-paragraph">
                                Absolument pas !<br />En réalité je sais m’adapter à toutes les technologies que l’on me donne. Pour peu qu’il soit
                                correctement documenté, apprendre un nouveau framework est toujours sympa.<br /><br />Ma spécialisation sur Laravel
                                vient de mon premier emploi et de mon expérience avec le développement d’applications en PHP.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                    <div class="flex flex-col gap-4">
                        <div class="text-design-system-title text-xl font-bold">Quand ai-je commencé la programmation ?</div>
                        <p class="text-design-system-paragraph">
                            J’ai commencé la programmation au travers du développement web vers 2015 - 2016. Au départ je maintenais un blog du nom
                            d’Actu-Gamework(.com), puis j’ai commencé à réaliser mon propre site peu de temps après avoir commencé la création de maps
                            pour Garry’s Mod. <br /><br />C’est ainsi qu’est né SL-Projects, pour Sofiane Lasri’s projects. :)
                        </p>
                    </div>
                    <div class="flex flex-col gap-4">
                        <div class="text-design-system-title text-xl font-bold">Pourquoi le développement web ?</div>
                        <p class="text-design-system-paragraph">
                            À vrai dire c’est un choix à moitié volontaire et à moitié subis. Si vous avez parcouru la liste des projets que je
                            présente sur le site, vous aurez peut-être remarqué que j’ai réalisé plusieurs prototypes de jeux vidéos.<br /><br />J’aurais
                            beaucoup aimé travailler dans le milieu du JV. Malheureusement, ce monde étant très fermé et très complexe, j’ai dû
                            m’orienter sur ce que je maîtrisais le mieux.
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                <div class="flex flex-col gap-4">
                    <div class="text-design-system-title h-7 w-96 justify-center text-2xl font-bold">Vous souhaitez me contacter ?</div>
                    <p class="text-design-system-paragraph">
                        Le meilleur moyen de me contacter est de m’envoyer un message sur LinkedIn. En effet, afin d’éviter au maximum le spam, je ne
                        publie pas mon numéro de téléphone et mon adresse e-mail (bien qu’elle soit facile à trouver).<br /><br />Notez que je ne
                        réponds pas aux demandes de contact concernant mes maps Garry’s Mod. Sauf si mention contraire, mon travail est libre de droit
                        et disponible sous les licences Creative Common.
                    </p>
                </div>
                <div class="flex flex-col gap-4">
                    <div class="text-design-system-title text-design-system-title h-7 justify-center text-2xl font-bold">
                        Est-ce que je propose mes services ?
                    </div>
                    <p class="text-design-system-paragraph">
                        Non, je ne propose pas mes services. Aussi bien en la qualité de développeur qu’en la qualité de map maker.
                        <span class="font-bold">Toutefois</span>, si vous rencontrez des problèmes sur l’une de mes créations et que vous avez besoin
                        d’aide (hors aide en mapping, désolé), je peux intervenir dans la mesure du possible.
                    </p>
                </div>
            </div>
        </div>
    </PublicAppLayout>
</template>
