<script setup lang="ts">
import { SSRVideo } from '@/types';
import { Play } from 'lucide-vue-next';
import { ref } from 'vue';
import ProjectVideoModal from './ProjectVideoModal.vue';

defineProps<{
    videos: SSRVideo[];
}>();

const selectedVideo = ref<SSRVideo | null>(null);
const isModalOpen = ref(false);

const openVideoModal = (video: SSRVideo) => {
    selectedVideo.value = video;
    isModalOpen.value = true;
};

const closeVideoModal = () => {
    isModalOpen.value = false;
    selectedVideo.value = null;
};
</script>

<template>
    <div class="w-full">
        <div class="scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-gray-100 flex gap-4 overflow-x-auto pb-4">
            <div v-for="video in videos" :key="video.id" class="group flex-shrink-0 cursor-pointer" @click="openVideoModal(video)">
                <div
                    class="relative aspect-video w-80 overflow-hidden rounded-lg bg-gray-100 shadow-md transition-all duration-300 group-hover:scale-[1.02] hover:shadow-lg"
                >
                    <img :src="video.coverPicture.avif.medium" :alt="video.name" class="h-full w-full object-cover" loading="lazy" />

                    <div
                        class="absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                    >
                        <div class="rounded-full bg-white/90 p-4 shadow-lg backdrop-blur-sm">
                            <Play class="ml-1 size-8 text-gray-900" fill="currentColor" />
                        </div>
                    </div>
                </div>

                <div class="mt-3 px-1">
                    <h4 class="text-design-system-paragraph group-hover:text-primary line-clamp-2 text-sm font-medium transition-colors">
                        {{ video.name }}
                    </h4>
                </div>
            </div>
        </div>

        <ProjectVideoModal :video="selectedVideo" :is-open="isModalOpen" @close="closeVideoModal" />
    </div>
</template>

<style scoped>
/* Custom scrollbar styles for better appearance */
.scrollbar-thin {
    scrollbar-width: thin;
}

.scrollbar-thin::-webkit-scrollbar {
    height: 6px;
}

.scrollbar-thin::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 3px;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Line clamp utility */
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
