<script setup lang="ts">
import LightShape from '@/components/public/LightShape.vue';
import BlackLinkButton from '@/components/public/Ui/Button/BlackLinkButton.vue';
import WhiteLinkButton from '@/components/public/Ui/Button/WhiteLinkButton.vue';
import { SSRFullCreation } from '@/types';

defineProps<{
    creation: SSRFullCreation;
}>();
</script>
<template>
    <div class="flex items-center justify-between gap-8 xl:gap-32">
        <div class="flex grow flex-col gap-16">
            <div class="flex flex-col gap-6 self-stretch">
                <div class="inline-flex justify-between self-stretch">
                    <div class="bg-gray-0 flex size-24 items-center justify-center gap-2.5 overflow-hidden rounded-lg border p-4">
                        <img class="flex-1 self-stretch" :src="creation.logo" alt="Logo du projet" />
                    </div>
                    <div class="flex w-64 flex-col items-end" v-if="creation.endedAt">
                        <div class="text-design-system-paragraph justify-center font-medium">Réalisé en</div>
                        <div class="text-design-system-paragraph justify-center text-2xl font-bold">
                            {{ new Date(creation.endedAt).getFullYear() }}
                        </div>
                    </div>
                    <div class="flex w-64 flex-col items-end" v-else>
                        <div class="text-design-system-paragraph justify-center font-medium">Réalisation</div>
                        <div class="text-design-system-paragraph justify-center text-2xl font-bold">En cours</div>
                    </div>
                </div>
                <div class="flex flex-col gap-2 self-stretch">
                    <div class="text-design-system-title justify-center self-stretch text-5xl font-bold">
                        {{ creation.name }}
                    </div>
                    <div class="text-design-system-paragraph justify-center self-stretch text-xl font-bold">
                        {{ creation.type }}
                    </div>
                </div>
                <div class="text-design-system-paragraph justify-center self-stretch text-xl font-normal">
                    {{ creation.shortDescription }}
                </div>
            </div>
            <div class="inline-flex items-center gap-4">
                <BlackLinkButton v-if="creation.externalUrl" :href="creation.externalUrl"> Visiter le site internet </BlackLinkButton>
                <WhiteLinkButton v-if="creation.sourceCodeUrl" :href="creation.sourceCodeUrl"> Code source </WhiteLinkButton>
            </div>
        </div>
        <div class="relative">
            <LightShape class="absolute top-0 -left-64 z-0" />
            <div
                class="bg-action-container-outer-color action-container-outer-shadow action-container-outer-border action-container-background-blur shrink-0 overflow-hidden rounded-3xl p-2"
            >
                <div
                    class="action-container-inner-shadow flex aspect-video h-36 flex-1 flex-col items-center justify-center overflow-hidden rounded-2xl border bg-white lg:h-[360px] 2xl:h-[480px]"
                >
                    <img class="h-full w-full object-cover" :src="creation.coverImage" alt="Image de couverture" />
                </div>
            </div>
        </div>
    </div>
</template>
