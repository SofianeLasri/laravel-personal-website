<script setup lang="ts">
import { SSRVideo } from '@/types';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { X } from 'lucide-vue-next';

const props = defineProps<{
    video: SSRVideo | null;
    isOpen: boolean;
}>();

const emit = defineEmits<{
    close: [];
}>();

const modalRef = ref<HTMLElement | null>(null);
const iframeRef = ref<HTMLIFrameElement | null>(null);

const iframeUrl = computed(() => {
    if (!props.video) return '';
    return `https://iframe.mediadelivery.net/embed/${props.video.libraryId}/${props.video.bunnyVideoId}`;
});

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        emit('close');
    }
};

const handleBackdropClick = (event: MouseEvent) => {
    if (event.target === modalRef.value) {
        emit('close');
    }
};

watch(
    () => props.isOpen,
    (isOpen) => {
        if (isOpen) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
            // Reset iframe when closing
            if (iframeRef.value) {
                iframeRef.value.src = '';
            }
        }
    },
);

onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-300"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isOpen && video"
                ref="modalRef"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm"
                @click="handleBackdropClick"
            >
                <div class="relative max-h-full w-full max-w-6xl">
                    <!-- Close button -->
                    <button
                        @click="emit('close')"
                        class="absolute -top-12 right-0 z-10 flex size-10 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur-sm transition-colors hover:bg-white/20"
                        aria-label="Fermer la vidéo"
                    >
                        <X class="size-6" />
                    </button>

                    <!-- Video container -->
                    <div class="relative aspect-video w-full overflow-hidden rounded-lg bg-black shadow-2xl">
                        <iframe
                            ref="iframeRef"
                            v-if="isOpen"
                            :src="iframeUrl"
                            class="h-full w-full"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                        />
                    </div>

                    <!-- Video title -->
                    <div class="mt-4 text-center">
                        <h3 class="text-lg font-semibold text-white">{{ video.name }}</h3>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>