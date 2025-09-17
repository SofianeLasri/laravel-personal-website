<script setup lang="ts">
import ExpandSolid from '@/components/font-awesome/ExpandSolid.vue';
import { SSRPicture } from '@/types';
import PhotoSwipeDynamicCaption from 'photoswipe-dynamic-caption-plugin';
import 'photoswipe-dynamic-caption-plugin/photoswipe-dynamic-caption-plugin.css';
import PhotoSwipeLightbox from 'photoswipe/lightbox';
import 'photoswipe/style.css';
import { computed, nextTick, onMounted } from 'vue';

interface Props {
    pictures: SSRPicture[];
}

const props = defineProps<Props>();

const galleryId = computed(() => `blog-gallery-${props.pictures[0]?.id || 'default'}`);

const gridClasses = computed(() => {
    if (props.pictures.length === 2) return 'grid-cols-1 md:grid-cols-2';
    if (props.pictures.length === 3) return 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3';
    if (props.pictures.length >= 4) return 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4';
    return 'grid-cols-1';
});

onMounted(() => {
    nextTick(() => {
        const lightbox = new PhotoSwipeLightbox({
            gallery: `#${galleryId.value}`,
            children: 'a',
            pswpModule: () => import('photoswipe'),
        });
        new PhotoSwipeDynamicCaption(lightbox, {
            type: 'auto',
        });

        lightbox.init();
    });
});
</script>

<template>
    <div class="space-y-4">
        <!-- Galerie PhotoSwipe - Structure simplifiée -->
        <div :id="galleryId" class="grid w-full gap-4" :class="[pictures.length === 1 ? 'grid-cols-1' : gridClasses]">
            <a
                v-for="picture in pictures"
                :key="picture.id"
                :href="picture.avif.full"
                :data-pswp-width="picture.width"
                :data-pswp-height="picture.height"
                :data-pswp-caption="picture.caption"
                target="_blank"
                class="group relative block overflow-hidden rounded-2xl shadow-[0px_0.25rem_0.5rem_0px_rgba(0,0,0,0.25)] focus:ring-2"
                :class="[pictures.length === 1 ? 'w-full' : 'aspect-square']"
            >
                <picture class="h-full w-full">
                    <source :srcset="pictures.length === 1 ? picture.avif.large : picture.avif.medium" type="image/avif" />
                    <source :srcset="pictures.length === 1 ? picture.webp.large : picture.webp.medium" type="image/webp" />
                    <img
                        :src="pictures.length === 1 ? picture.webp.large : picture.webp.medium"
                        :alt="picture.caption"
                        loading="lazy"
                        class="object-cover"
                        :class="[pictures.length === 1 ? 'h-auto w-full' : 'h-full w-full']"
                    />
                </picture>

                <!-- Overlay -->
                <div
                    class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                >
                    <ExpandSolid class="size-8 fill-white" />
                </div>
            </a>
        </div>

        <!-- Légendes -->
        <div v-if="pictures.some((p) => p.caption)" class="space-y-1">
            <!-- Image unique -->
            <div v-if="pictures.length === 1 && pictures[0].caption" class="mt-4 text-center">
                <p class="text-design-system-paragraph mx-auto max-w-2xl px-4 text-gray-600 italic">
                    {{ pictures[0].caption }}
                </p>
            </div>

            <!-- Images multiples -->
            <div v-else-if="pictures.length > 1" class="text-design-system-paragraph space-y-1 text-sm">
                <p v-for="(picture, index) in pictures.filter((p) => p.caption)" :key="picture.id" class="italic">
                    <span class="font-medium">{{ index + 1 }}.</span> {{ picture.caption }}
                </p>
            </div>
        </div>
    </div>
</template>
