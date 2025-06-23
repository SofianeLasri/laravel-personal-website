<script setup lang="ts">
import ExpandSolid from '@/components/font-awesome/ExpandSolid.vue';
import { SSRScreenshot } from '@/types';
import PhotoSwipeDynamicCaption from 'photoswipe-dynamic-caption-plugin';
import 'photoswipe-dynamic-caption-plugin/photoswipe-dynamic-caption-plugin.css';
import PhotoSwipeLightbox from 'photoswipe/lightbox';
import 'photoswipe/style.css';
import { onMounted } from 'vue';

defineProps<{
    screenshots: SSRScreenshot[];
}>();

onMounted(() => {
    const lightbox = new PhotoSwipeLightbox({
        gallery: `#medias`,
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
    <div id="medias" class="grid w-full grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <a
            v-for="screenshot in screenshots"
            :key="screenshot.id"
            :href="screenshot.picture.avif.full"
            :data-pswp-width="screenshot.picture.width"
            :data-pswp-height="screenshot.picture.height"
            target="_blank"
            class="relative flex aspect-square w-full shrink-0 overflow-hidden rounded-2xl focus:ring-2"
        >
            <picture class="h-full w-full">
                <source :srcset="screenshot.picture.webp.medium" type="image/webp" />
                <img
                    :src="screenshot.picture.avif.medium"
                    :alt="screenshot.caption || 'Screenshot'"
                    loading="lazy"
                    class="h-full w-full object-cover"
                />
            </picture>

            <!-- Overlay -->
            <div class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 transition-opacity duration-300 hover:opacity-100">
                <div class="text-white">
                    <ExpandSolid class="size-8 fill-white" />
                </div>
            </div>
        </a>
    </div>
</template>

<style scoped></style>
