<script setup lang="ts">
import { Play } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface VideoProp {
    id: number;
    bunnyVideoId: string;
    name: string;
    coverPicture: {
        filename: string;
        width: number | null;
        height: number | null;
        avif: {
            thumbnail: string;
            small: string;
            medium: string;
            large: string;
            full: string;
        };
        webp: {
            thumbnail: string;
            small: string;
            medium: string;
            large: string;
            full: string;
        };
        jpg: {
            thumbnail: string;
            small: string;
            medium: string;
            large: string;
            full: string;
        };
    };
    libraryId: string;
    caption: string | null;
}

const props = defineProps<{
    video: VideoProp;
}>();

const isPlaying = ref(false);
const iframeRef = ref<HTMLIFrameElement | null>(null);

const iframeUrl = computed(() => {
    return `https://iframe.mediadelivery.net/embed/${props.video.libraryId}/${props.video.bunnyVideoId}`;
});

const playVideo = () => {
    isPlaying.value = true;
};
</script>

<template>
    <div class="w-full space-y-4">
        <!-- Video Container -->
        <div class="relative aspect-video w-full overflow-hidden rounded-lg bg-black shadow-lg">
            <!-- Cover Image with Play Button -->
            <Transition
                enter-active-class="transition-opacity duration-300"
                enter-from-class="opacity-100"
                enter-to-class="opacity-0"
                leave-active-class="transition-opacity duration-300"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="!isPlaying" class="absolute inset-0 z-10 cursor-pointer group" @click="playVideo">
                    <picture class="h-full w-full">
                        <source :srcset="video.coverPicture.avif.large" type="image/avif" />
                        <source :srcset="video.coverPicture.webp.large" type="image/webp" />
                        <img
                            :src="video.coverPicture.webp.large"
                            :alt="`Miniature - ${video.name}`"
                            class="h-full w-full object-cover"
                            loading="lazy"
                        />
                    </picture>

                    <!-- Play Button Overlay -->
                    <div class="absolute inset-0 flex items-center justify-center bg-black/30 transition-all duration-300 group-hover:bg-black/40">
                        <div class="rounded-full bg-white/90 p-6 shadow-2xl backdrop-blur-sm transition-transform duration-300 group-hover:scale-110">
                            <Play class="ml-1 size-12 text-gray-900" fill="currentColor" />
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Video Iframe -->
            <iframe
                v-if="isPlaying"
                ref="iframeRef"
                :src="iframeUrl"
                class="absolute inset-0 h-full w-full"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
            />
        </div>

        <!-- Caption -->
        <div v-if="video.caption" class="text-design-system-paragraph text-center text-sm italic">
            {{ video.caption }}
        </div>
    </div>
</template>
