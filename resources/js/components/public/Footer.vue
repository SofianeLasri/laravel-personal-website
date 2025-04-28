<script setup lang="ts">
import Logo from '@/components/public/Logo.vue';
import { SocialMediaLink } from '@/types';

defineProps<{
    socialMediaLinks: SocialMediaLink[];
}>();
</script>

<template>
    <div class="relative flex flex-col overflow-hidden bg-black">
        <div class="border-border flex w-full flex-col items-center justify-start gap-2.5 rounded-br-2xl rounded-bl-2xl border-t bg-gray-100 py-16">
            <div class="container flex items-center justify-between">
                <div class="flex flex-col gap-4">
                    <Logo class="h-8" />
                    <div class="flex flex-col justify-start">
                        <div class="text-xl font-bold text-black">Sofiane Lasri</div>
                        <div class="text-design-system-paragraph justify-center text-base font-normal">Développeur Full-Stack</div>
                    </div>
                </div>
                <div class="flex justify-end gap-16">
                    <div class="flex flex-col justify-start gap-4">
                        <div class="justify-center text-xl font-bold text-black">Portfolio.</div>
                        <ul class="text-design-system-paragraph justify-start text-base font-normal">
                            <li class="list-none">
                                <a href="/" title="Accueil">Accueil</a>
                            </li>
                            <li class="list-none">
                                <a href="/projects" title="Mes projets">Projets</a>
                            </li>
                            <li class="list-none">
                                <a href="/career" title="Mon parcours professionnel & scolaire">Parcours</a>
                            </li>
                            <li class="list-none">
                                <a href="/about" title="À propos de moi">À propos</a>
                            </li>
                        </ul>
                    </div>
                    <div class="flex w-36 flex-col justify-start gap-4">
                        <div class="justify-center text-xl font-bold text-black">Me retrouver.</div>
                        <ul class="text-design-system-paragraph justify-start text-base font-normal">
                            <li class="list-none" v-for="link in socialMediaLinks" :key="link.id">
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
        <div class="flex w-full flex-col items-center justify-start gap-2.5">
            <div class="container flex h-12 items-center justify-start gap-2.5">
                <div class="text-white">2025 - Sofiane Lasri-Trienpont, développé sur Laravel.</div>
            </div>
        </div>
    </div>
</template>

<style scoped></style>
