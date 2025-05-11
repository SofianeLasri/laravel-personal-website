<script setup lang="ts">
import LightShape from '@/components/public/LightShape.vue';
import ProjectHead from '@/components/public/ProjectPage/ProjectHead.vue';
import PublicAppLayout from '@/layouts/PublicAppLayout.vue';
import { SocialMediaLink, SSRFullCreation } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{
    socialMediaLinks: SocialMediaLink[];
    creation: SSRFullCreation;
}>();

const activeSection = 'description';
</script>

<template>
    <Head title="Creation" />
    <PublicAppLayout :socialMediaLinks="socialMediaLinks">
        <div class="absolute top-0 left-0 z-0 h-full w-full overflow-hidden">
            <LightShape class="absolute top-0 left-[-27rem] xl:left-[-15rem]" />
        </div>

        <div class="z-10 container flex flex-col gap-16">
            <ProjectHead :creation="creation" />

            <!-- Barre de navigation -->
            <div class="border-b border-gray-200">
                <div class="flex space-x-8">
                    <button
                        class="cursor-pointer border-b-2 py-4 text-xl transition-colors"
                        :class="activeSection === 'description' ? 'border-black text-black' : 'border-transparent text-gray-500 hover:text-black'"
                    >
                        Description
                    </button>
                    <button
                        class="cursor-pointer border-b-2 py-4 text-xl transition-colors"
                        :class="activeSection === 'features' ? 'border-black text-black' : 'border-transparent text-gray-500 hover:text-black'"
                        v-if="creation.features.length"
                    >
                        Fonctionnalités clés
                    </button>
                    <button
                        class="cursor-pointer border-b-2 py-4 text-xl transition-colors"
                        :class="activeSection === 'technologies' ? 'border-black text-black' : 'border-transparent text-gray-500 hover:text-black'"
                        v-if="creation.technologies.length"
                    >
                        Technologies utilisées
                    </button>
                    <button
                        class="cursor-pointer border-b-2 py-4 text-xl transition-colors"
                        :class="activeSection === 'screnshots' ? 'border-black text-black' : 'border-transparent text-gray-500 hover:text-black'"
                        v-if="creation.screenshots.length"
                    >
                        Capture d'écrans
                    </button>
                </div>
            </div>
            <!-- Fin de la barre de navigation -->
        </div>
    </PublicAppLayout>
</template>
