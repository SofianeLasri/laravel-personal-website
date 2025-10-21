<script setup lang="ts">
import VideoManager from '@/components/dashboard/VideoManager.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useRoute } from '@/composables/useRoute';
import type { BlogContentVideo, Video } from '@/types';
import axios from 'axios';
import { Edit, FileVideo, ImageDown, Loader2, Plus, Trash2, Upload } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    blogContentVideoId: number;
    locale: 'fr' | 'en';
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'video-selected', videoId: number): void;
    (e: 'video-removed'): void;
}>();

const route = useRoute();

// State
const blogContentVideo = ref<BlogContentVideo | null>(null);
const currentVideo = ref<Video | null>(null);
const allVideos = ref<Video[]>([]);
const loading = ref(false);
const loadingThumbnail = ref(false);
const caption = ref('');
const captionSaving = ref(false);
const captionSaveTimeout = ref<ReturnType<typeof setTimeout> | null>(null);

// Modals
const isSelectModalOpen = ref(false);
const isUploadModalOpen = ref(false);
const isRemoveDialogOpen = ref(false);

// Ref to VideoManager for helpers
// eslint-disable-next-line @typescript-eslint/no-redundant-type-constituents
const videoManager = ref<InstanceType<typeof VideoManager> | null>(null);

// Load blog content video data
const loadBlogContentVideo = async () => {
    if (!props.blogContentVideoId) return;

    loading.value = true;
    try {
        const response = await axios.get(
            route('dashboard.api.blog-content-video.show', {
                blog_content_video: props.blogContentVideoId,
            }),
        );

        blogContentVideo.value = response.data;

        // Load video details if video is attached
        if (response.data.video_id) {
            await loadVideo(response.data.video_id);
        }

        // Load caption for current locale
        const captionTranslation = response.data.caption_translation_key?.translations?.find((t: { locale: string }) => t.locale === props.locale);
        caption.value = captionTranslation?.text ?? '';
    } catch (error) {
        console.error('Error loading blog content video:', error);
        toast.error('Erreur lors du chargement de la vidéo');
    } finally {
        loading.value = false;
    }
};

// Load video details
const loadVideo = async (videoId: number) => {
    try {
        const response = await axios.get(route('dashboard.api.videos.show', { video: videoId }));
        currentVideo.value = response.data;
    } catch (error) {
        console.error('Error loading video:', error);
    }
};

// Load all available videos
const fetchAllVideos = async () => {
    try {
        const response = await axios.get(route('dashboard.api.videos.index'));
        allVideos.value = response.data;
    } catch (error) {
        console.error('Error loading available videos:', error);
    }
};

// Open remove video dialog
const confirmRemoveVideo = () => {
    isRemoveDialogOpen.value = true;
};

// Remove video
const removeVideo = async () => {
    loading.value = true;
    isRemoveDialogOpen.value = false;

    try {
        // Remove video from blog content video
        await axios.put(
            route('dashboard.api.blog-content-video.update', {
                blog_content_video: props.blogContentVideoId,
            }),
            {
                video_id: null,
                caption: caption.value,
                locale: props.locale,
            },
        );

        currentVideo.value = null;
        if (blogContentVideo.value) {
            blogContentVideo.value.video_id = null;
        }
        emit('video-removed');
        toast.success('Vidéo retirée');
    } catch (error) {
        console.error('Error removing video:', error);
        toast.error('Erreur lors de la suppression de la vidéo');
    } finally {
        loading.value = false;
    }
};

// Save caption with debouncing
const saveCaption = async () => {
    if (!blogContentVideo.value) return;

    captionSaving.value = true;
    try {
        await axios.put(
            route('dashboard.api.blog-content-video.update', {
                blog_content_video: props.blogContentVideoId,
            }),
            {
                video_id: blogContentVideo.value.video_id,
                caption: caption.value,
                locale: props.locale,
            },
        );
    } catch (error) {
        console.error('Error saving caption:', error);
        toast.error('Erreur lors de la sauvegarde de la description');
    } finally {
        captionSaving.value = false;
    }
};

// Debounced caption update
const updateCaption = (value: string) => {
    caption.value = value;

    // Clear existing timeout
    if (captionSaveTimeout.value) {
        clearTimeout(captionSaveTimeout.value);
    }

    // Set new timeout to save after 2.5 seconds
    captionSaveTimeout.value = setTimeout(() => {
        void saveCaption();
    }, 2500);
};

// VideoManager event handlers

const handleVideoUploaded = async (video: Video) => {
    // Update blog content video with uploaded video
    await axios.put(
        route('dashboard.api.blog-content-video.update', {
            blog_content_video: props.blogContentVideoId,
        }),
        {
            video_id: video.id,
            caption: caption.value,
            locale: props.locale,
        },
    );

    // Reload data
    await loadBlogContentVideo();
    emit('video-selected', video.id);
};

const handleVideoSelected = async (videoId: number) => {
    // Update blog content video with selected video
    await axios.put(
        route('dashboard.api.blog-content-video.update', {
            blog_content_video: props.blogContentVideoId,
        }),
        {
            video_id: videoId,
            caption: caption.value,
            locale: props.locale,
        },
    );

    // Reload data
    await loadBlogContentVideo();
    emit('video-selected', videoId);
};

const handleThumbnailDownloaded = async (video: Video) => {
    // Reload video data to get updated cover picture
    await loadVideo(video.id);
};

// Open modals
const openSelectModal = async () => {
    await fetchAllVideos();
    isSelectModalOpen.value = true;
};

const openUploadModal = () => {
    isUploadModalOpen.value = true;
};

// Lifecycle
onMounted(() => {
    void loadBlogContentVideo();
});

onUnmounted(() => {
    if (captionSaveTimeout.value) {
        clearTimeout(captionSaveTimeout.value);
    }
});

// Watch for blog content video ID changes
watch(
    () => props.blogContentVideoId,
    async (newId, oldId) => {
        if (newId && newId !== oldId) {
            await loadBlogContentVideo();
        }
    },
);
</script>

<template>
    <div class="space-y-4">
        <!-- Current Video Display -->
        <div v-if="currentVideo" class="space-y-4">
            <Card>
                <CardContent class="p-4">
                    <div class="flex items-start gap-4">
                        <!-- Video Preview -->
                        <div class="flex-shrink-0">
                            <div class="relative h-20 w-32 overflow-hidden rounded-lg bg-gray-100">
                                <img
                                    v-if="currentVideo.cover_picture"
                                    :src="`/storage/${currentVideo.cover_picture.path_original}`"
                                    :alt="currentVideo.name"
                                    class="h-full w-full object-cover"
                                />
                                <div v-else class="flex h-full w-full items-center justify-center">
                                    <FileVideo class="h-8 w-8 text-gray-400" />
                                </div>

                                <!-- Play overlay -->
                                <div class="absolute inset-0 flex items-center justify-center bg-black/30">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-white/90">
                                        <div class="ml-1 h-0 w-0 border-y-2 border-l-4 border-y-transparent border-l-gray-800"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Video Info -->
                        <div class="flex-1">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h3 class="text-sm font-medium">{{ currentVideo.name }}</h3>
                                    <div v-if="videoManager" class="mt-1 flex items-center gap-2 text-xs">
                                        <span :class="videoManager.getStatusColor(currentVideo.status)" class="font-medium">
                                            {{ videoManager.getStatusLabel(currentVideo.status) }}
                                        </span>
                                        <span class="text-gray-400">•</span>
                                        <span class="text-gray-500">{{ currentVideo.visibility === 'public' ? 'Publique' : 'Privée' }}</span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <Button
                                        v-if="currentVideo.status === 'ready' && videoManager"
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        :disabled="loadingThumbnail"
                                        title="Télécharger la miniature depuis BunnyCDN"
                                        @click="videoManager.downloadThumbnail(currentVideo)"
                                    >
                                        <Loader2 v-if="loadingThumbnail" class="h-4 w-4 animate-spin" />
                                        <ImageDown v-else class="h-4 w-4" />
                                    </Button>
                                    <Button type="button" variant="ghost" size="sm" @click="openSelectModal">
                                        <Edit class="h-4 w-4" />
                                    </Button>
                                    <Button type="button" variant="ghost" size="sm" @click="confirmRemoveVideo">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Caption Input -->
                    <div class="mt-4 space-y-2">
                        <div class="flex items-center justify-between">
                            <Label class="text-sm">Description ({{ locale.toUpperCase() }})</Label>
                            <div v-if="captionSaving" class="flex items-center gap-1 text-xs text-blue-600">
                                <div class="h-3 w-3 animate-spin rounded-full border-b-2 border-blue-600"></div>
                                Sauvegarde...
                            </div>
                        </div>
                        <Textarea
                            :value="caption"
                            placeholder="Ajoutez une description pour cette vidéo..."
                            class="min-h-[60px] text-sm"
                            @input="(e: Event) => updateCaption((e.target as HTMLTextAreaElement).value)"
                        />
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- No Video State -->
        <div v-else-if="!loading" class="space-y-4">
            <div class="rounded-lg border-2 border-dashed border-gray-300 py-8 text-center">
                <FileVideo class="mx-auto mb-4 h-12 w-12 text-gray-400" />
                <p class="mb-4 text-sm text-gray-600">Aucune vidéo sélectionnée</p>

                <div class="flex justify-center gap-2">
                    <Button type="button" variant="outline" size="sm" @click="openSelectModal">
                        <Plus class="mr-2 h-4 w-4" />
                        Sélectionner une vidéo
                    </Button>
                    <Button type="button" variant="outline" size="sm" @click="openUploadModal">
                        <Upload class="mr-2 h-4 w-4" />
                        Uploader une vidéo
                    </Button>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center py-8">
            <Loader2 class="h-8 w-8 animate-spin text-gray-400" />
        </div>

        <!-- VideoManager with all modals -->
        <VideoManager
            ref="videoManager"
            v-model:show-select-modal="isSelectModalOpen"
            v-model:show-upload-modal="isUploadModalOpen"
            :available-videos="allVideos"
            :allow-thumbnail-download="true"
            @video-uploaded="handleVideoUploaded"
            @video-selected="handleVideoSelected"
            @thumbnail-downloaded="handleThumbnailDownloaded"
        />

        <!-- Remove Video Confirmation Dialog -->
        <AlertDialog v-model:open="isRemoveDialogOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Êtes-vous sûr de vouloir retirer cette vidéo ?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Cette action retirera la vidéo de ce contenu. La vidéo ne sera pas supprimée définitivement et pourra être réutilisée.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Annuler</AlertDialogCancel>
                    <AlertDialogAction @click="removeVideo">
                        <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                        Retirer
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
