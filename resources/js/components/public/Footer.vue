<script setup lang="ts">
import Logo from '@/components/public/Logo.vue';
import { SocialMediaLink } from '@/types';
import { Link } from '@inertiajs/vue3';

defineProps<{
    socialMediaLinks: SocialMediaLink[];
}>();
</script>

<template>
    <div class="relative flex flex-col overflow-hidden bg-black">
        <div
            class="border-border flex w-full flex-col items-center justify-start gap-2.5 rounded-br-2xl rounded-bl-2xl border-t bg-gray-100 py-8 md:py-16"
        >
            <div class="container space-y-8 px-4 md:space-y-0">
                <!-- Section principale du footer -->
                <div class="flex flex-col gap-8 md:flex-row md:items-center md:justify-between md:gap-4">
                    <!-- Logo et informations -->
                    <div class="flex flex-col gap-4">
                        <Logo class="h-8" />
                        <div class="flex flex-col justify-start">
                            <div class="text-xl font-bold text-black">Sofiane Lasri</div>
                            <div class="text-design-system-paragraph text-base font-normal">Développeur Full-Stack</div>
                        </div>
                    </div>

                    <!-- Colonnes de liens -->
                    <div class="flex flex-col justify-start gap-8 sm:flex-row md:justify-end md:gap-16">
                        <!-- Colonne Portfolio -->
                        <div class="flex flex-col justify-start gap-4">
                            <div class="text-xl font-bold text-black">Portfolio.</div>
                            <ul class="text-design-system-paragraph text-base font-normal">
                                <li class="mb-2 list-none">
                                    <Link :href="route('home')" title="Accueil">Accueil</Link>
                                </li>
                                <li class="mb-2 list-none">
                                    <Link :href="route('projects')" title="Mes projets">Projets</Link>
                                </li>
                                <li class="mb-2 list-none">
                                    <a href="/career" title="Mon parcours professionnel & scolaire">Parcours</a>
                                </li>
                                <li class="mb-2 list-none">
                                    <a href="/about" title="À propos de moi">À propos</a>
                                </li>
                            </ul>
                        </div>

                        <!-- Colonne Me retrouver -->
                        <div class="flex flex-col justify-start gap-4">
                            <div class="text-xl font-bold text-black">Me retrouver.</div>
                            <ul class="text-design-system-paragraph text-base font-normal">
                                <li class="mb-2 list-none" v-for="link in socialMediaLinks" :key="link.id">
                                    <a :href="link.url" :title="link.name" target="_blank">
                                        <div class="flex items-center justify-start gap-2">
                                            <div class="flex h-4 fill-black" v-html="link.icon_svg"></div>
                                            <span>{{ link.name }}</span>
                                        </div>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Copyright -->
        <div class="flex w-full flex-col items-center justify-start gap-2.5">
            <div class="container flex h-12 items-center justify-center px-4 text-center md:justify-start md:px-0 md:text-left">
                <div class="text-sm text-white md:text-base">2025 - Sofiane Lasri-Trienpont, développé sur Laravel.</div>
            </div>
        </div>
    </div>
</template>

<style scoped></style>
