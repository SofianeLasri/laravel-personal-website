<script setup lang="ts">
import { useTranslation } from '@/composables/useTranslation';
import { SSRSimplifiedCreation } from '@/types';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import Skeleton from '@/components/ui/skeleton/Skeleton.vue';

const props = defineProps<{
    creation: SSRSimplifiedCreation;
}>();

const { t } = useTranslation();
const isImageLoaded = ref(false);

const handleImageLoad = () => {
    isImageLoaded.value = true;
};

const startYear = new Date(props.creation.startedAt).getFullYear();
const endYear = props.creation.endedAt ? new Date(props.creation.endedAt).getFullYear() : null;

let formattedYears: string;

if (startYear && endYear) {
    formattedYears = `${startYear} - ${endYear}`;
} else {
    formattedYears = `${startYear} - Aujd`;
}
</script>

<template>
    <div class="flex w-full flex-shrink-0 flex-col gap-4 select-none md:w-[40rem]">
        <Link
            class="relative flex aspect-video flex-col gap-2.5 overflow-hidden rounded-2xl shadow-[0px_0.25rem_0.5rem_0px_rgba(0,0,0,0.25)]"
            :href="route('public.projects.show', { slug: creation.slug })"
        >
            <Skeleton v-if="!isImageLoaded" class="absolute inset-0 z-10" />
            <picture class="h-full w-full">
                <source :srcset="creation.coverImage.webp.medium" type="image/webp" />
                <img
                    :src="creation.coverImage.avif.medium"
                    alt="Project cover"
                    class="h-full w-full object-cover"
                    loading="lazy"
                    @load="handleImageLoad"
                />
            </picture>
        </Link>
        <div class="flex gap-4 rounded-2xl">
            <Link
                class="flex size-20 flex-shrink-0 items-center justify-center gap-2.5 rounded-lg border bg-white p-3 shadow-[0px_0.25rem_0.5rem_0px_rgba(0,0,0,0.25)] md:p-4"
                :href="route('public.projects.show', { slug: creation.slug })"
            >
                <picture class="flex-1">
                    <source :srcset="creation.logo.webp.thumbnail" type="image/webp" />
                    <img :src="creation.logo.avif.thumbnail" alt="Logo of the project" class="h-full w-full object-cover" loading="lazy" />
                </picture>
            </Link>
            <div class="flex flex-1 flex-col gap-4">
                <div class="flex flex-col gap-px">
                    <Link
                        class="text-design-system-title justify-center text-base font-bold"
                        :href="route('public.projects.show', { slug: creation.slug })"
                    >
                        {{ creation.name }}
                    </Link>
                    <div class="text-design-system-paragraph justify-center text-sm font-semibold" lang="fr">
                        {{ t(`projects.types.${creation.type}`) }}
                    </div>
                </div>
                <div class="text-design-system-paragraph justify-center text-sm font-normal">
                    {{ formattedYears }}
                </div>
            </div>
            <div class="hidden flex-col gap-2.5 py-2 md:flex">
                <div class="flex items-center gap-2">
                    <div v-for="technology in creation.technologies" :key="technology.name">
                        <div class="flex size-4 items-center justify-center overflow-hidden">
                            <img
                                :src="technology.iconPicture.webp.thumbnail"
                                :alt="`${technology.name} icon`"
                                class="h-full w-full object-contain"
                                loading="lazy"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
