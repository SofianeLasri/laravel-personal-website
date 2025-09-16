<script setup lang="ts">
import ExpandSolid from '@/components/font-awesome/ExpandSolid.vue';
import { SSRPicture } from '@/types';
import PhotoSwipeDynamicCaption from 'photoswipe-dynamic-caption-plugin';
import 'photoswipe-dynamic-caption-plugin/photoswipe-dynamic-caption-plugin.css';
import PhotoSwipeLightbox from 'photoswipe/lightbox';
import 'photoswipe/style.css';
import { onMounted } from 'vue';

interface Props {
    pictures: SSRPicture[];
}

const props = defineProps<Props>();

onMounted(() => {
    const lightbox = new PhotoSwipeLightbox({
        gallery: `#blog-gallery-${props.pictures[0]?.id || 'default'}`,
        children: 'a',
        pswpModule: () => import('photoswipe'),
    });
    new PhotoSwipeDynamicCaption(lightbox, {
        type: 'auto',
    });

    lightbox.init();
});
</script>

<template>
    <div class="space-y-4">
        <!-- Single Image - Full Width -->
        <div v-if="pictures.length === 1" class="space-y-3">
            <div :id="`blog-gallery-${pictures[0].id}`" class="w-full">
                <a
                    :href="pictures[0].avif.full"
                    :data-pswp-width="pictures[0].width"
                    :data-pswp-height="pictures[0].height"
                    target="_blank"
                    class="group relative block w-full overflow-hidden rounded-2xl shadow-[0px_0.25rem_0.5rem_0px_rgba(0,0,0,0.25)] focus:ring-2"
                >
                    <picture class="block w-full">
                        <source :srcset="pictures[0].avif.large" type="image/avif" />
                        <source :srcset="pictures[0].webp.large" type="image/webp" />
                        <img :src="pictures[0].webp.large" :alt="pictures[0].caption || 'Image'" loading="lazy" class="h-auto w-full object-cover" />
                    </picture>

                    <!-- Overlay -->
                    <div
                        class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                    >
                        <ExpandSolid class="size-8 fill-white" />
                    </div>
                </a>
            </div>

            <!-- Single Image Description -->
            <p v-if="pictures[0].caption" class="text-design-system-paragraph text-center italic">
                {{ pictures[0].caption }}
            </p>
        </div>

        <!-- Multiple Images - Responsive Grid -->
        <div v-else class="space-y-3">
            <div
                :id="`blog-gallery-${pictures[0]?.id || 'default'}`"
                class="grid w-full gap-4"
                :class="{
                    'grid-cols-1 md:grid-cols-2': pictures.length === 2,
                    'grid-cols-1 md:grid-cols-2 lg:grid-cols-3': pictures.length === 3,
                    'grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4': pictures.length >= 4,
                }"
            >
                <a
                    v-for="picture in pictures"
                    :key="picture.id"
                    :href="picture.avif.full"
                    :data-pswp-width="picture.width"
                    :data-pswp-height="picture.height"
                    target="_blank"
                    class="group relative block aspect-square overflow-hidden rounded-2xl shadow-[0px_0.25rem_0.5rem_0px_rgba(0,0,0,0.25)] focus:ring-2"
                >
                    <picture class="h-full w-full">
                        <source :srcset="picture.avif.medium" type="image/avif" />
                        <source :srcset="picture.webp.medium" type="image/webp" />
                        <img :src="picture.webp.medium" :alt="picture.caption || 'Image'" loading="lazy" class="h-full w-full object-cover" />
                    </picture>

                    <!-- Overlay -->
                    <div
                        class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                    >
                        <ExpandSolid class="size-8 fill-white" />
                    </div>
                </a>
            </div>

            <!-- Multiple Images - Captions -->
            <div v-if="pictures.some((p) => p.caption)" class="text-design-system-paragraph space-y-1 text-sm">
                <p v-for="(picture, index) in pictures.filter((p) => p.caption)" :key="picture.id" class="italic">
                    <span class="font-medium">{{ index + 1 }}.</span> {{ picture.caption }}
                </p>
            </div>
        </div>
    </div>
</template>