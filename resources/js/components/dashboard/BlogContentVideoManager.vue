<script setup lang="ts">
import PictureInput from '@/components/dashboard/PictureInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useRoute } from '@/composables/useRoute';
import type { BlogContentVideo, Video } from '@/types';
import axios from 'axios';
import { Download, Edit, FileVideo, ImageDown, Loader2, Plus, Trash2, Upload } from 'lucide-vue-next';
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
const selectedVideoId = ref<number | undefined>(undefined);
const newVideoFile = ref<File | null>(null);
const newVideoName = ref('');
const newVideoCoverPictureId = ref<number | undefined>(undefined);
const uploadProgress = ref(0);

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

// Select existing video
const openSelectModal = async () => {
    isSelectModalOpen.value = true;
    await fetchAllVideos();
};

// Confirm video selection
const confirmVideoSelection = async () => {
    if (!selectedVideoId.value) return;

    loading.value = true;
    try {
        // Update blog content video with selected video
        await axios.put(
            route('dashboard.api.blog-content-video.update', {
                blog_content_video: props.blogContentVideoId,
            }),
            {
                video_id: selectedVideoId.value,
                caption: caption.value,
                locale: props.locale,
            },
        );

        // Reload data
        await loadBlogContentVideo();
        emit('video-selected', selectedVideoId.value);

        isSelectModalOpen.value = false;
        selectedVideoId.value = undefined;
        toast.success('Vidéo sélectionnée avec succès');
    } catch (error) {
        console.error('Error selecting video:', error);
        toast.error('Erreur lors de la sélection de la vidéo');
    } finally {
        loading.value = false;
    }
};

// Upload new video
const openUploadModal = () => {
    isUploadModalOpen.value = true;
};

const uploadVideo = async () => {
    if (!newVideoFile.value) return;

    loading.value = true;
    uploadProgress.value = 0;

    try {
        // Upload video
        const formData = new FormData();
        formData.append('video', newVideoFile.value);
        formData.append('name', newVideoName.value || newVideoFile.value.name);
        if (newVideoCoverPictureId.value) {
            formData.append('cover_picture_id', newVideoCoverPictureId.value.toString());
        }

        const uploadResponse = await axios.post(route('dashboard.api.videos.store'), formData, {
            onUploadProgress: (progressEvent) => {
                if (progressEvent.total) {
                    uploadProgress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                }
            },
        });

        // Update blog content video with uploaded video
        await axios.put(
            route('dashboard.api.blog-content-video.update', {
                blog_content_video: props.blogContentVideoId,
            }),
            {
                video_id: uploadResponse.data.id,
                caption: caption.value,
                locale: props.locale,
            },
        );

        // Reload data
        await loadBlogContentVideo();
        emit('video-selected', uploadResponse.data.id);

        resetUploadForm();
        isUploadModalOpen.value = false;
        toast.success('Vidéo uploadée avec succès');
    } catch (error) {
        console.error('Error uploading video:', error);
        toast.error("Erreur lors de l'upload de la vidéo");
    } finally {
        loading.value = false;
        uploadProgress.value = 0;
    }
};

// Remove video
const removeVideo = async () => {
    if (!confirm('Êtes-vous sûr de vouloir retirer cette vidéo ?')) return;

    loading.value = true;
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
        blogContentVideo.value!.video_id = null;
        emit('video-removed');
        toast.success('Vidéo retirée');
    } catch (error) {
        console.error('Error removing video:', error);
        toast.error('Erreur lors de la suppression de la vidéo');
    } finally {
        loading.value = false;
    }
};

// Download thumbnail from Bunny CDN
const downloadThumbnail = async () => {
    if (!currentVideo.value) return;

    loadingThumbnail.value = true;
    try {
        await axios.post(
            route('dashboard.api.videos.download-thumbnail', {
                video: currentVideo.value.id,
            }),
        );

        // Reload video data to get updated cover picture
        await loadVideo(currentVideo.value.id);
        toast.success('Miniature téléchargée et définie comme image de couverture');
    } catch (error) {
        console.error('Error downloading thumbnail:', error);
        toast.error('Erreur lors du téléchargement de la miniature');
    } finally {
        loadingThumbnail.value = false;
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

// Reset forms
const resetUploadForm = () => {
    newVideoFile.value = null;
    newVideoName.value = '';
    newVideoCoverPictureId.value = undefined;
};

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files?.[0]) {
        newVideoFile.value = target.files[0];
        if (!newVideoName.value) {
            newVideoName.value = target.files[0].name.replace(/\.[^/.]+$/, '');
        }
    }
};

// Status helpers
const getStatusLabel = (status: string): string => {
    switch (status) {
        case 'pending':
            return 'En attente';
        case 'transcoding':
            return 'Transcodage en cours';
        case 'ready':
            return 'Prêt';
        case 'error':
            return 'Erreur';
        default:
            return status;
    }
};

const getStatusColor = (status: string): string => {
    switch (status) {
        case 'pending':
            return 'text-yellow-600';
        case 'transcoding':
            return 'text-blue-600';
        case 'ready':
            return 'text-green-600';
        case 'error':
            return 'text-red-600';
        default:
            return 'text-gray-600';
    }
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
                                    :src="`/storage/${currentVideo.cover_picture.path_small}`"
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
                                    <div class="mt-1 flex items-center gap-2 text-xs">
                                        <span :class="getStatusColor(currentVideo.status)" class="font-medium">
                                            {{ getStatusLabel(currentVideo.status) }}
                                        </span>
                                        <span class="text-gray-400">•</span>
                                        <span class="text-gray-500">{{ currentVideo.visibility === 'public' ? 'Publique' : 'Privée' }}</span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <Button
                                        v-if="currentVideo.status === 'ready'"
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        :disabled="loadingThumbnail"
                                        title="Télécharger la miniature depuis BunnyCDN"
                                        @click="downloadThumbnail"
                                    >
                                        <Loader2 v-if="loadingThumbnail" class="h-4 w-4 animate-spin" />
                                        <ImageDown v-else class="h-4 w-4" />
                                    </Button>
                                    <Button type="button" variant="ghost" size="sm" @click="openSelectModal">
                                        <Edit class="h-4 w-4" />
                                    </Button>
                                    <Button type="button" variant="ghost" size="sm" @click="removeVideo">
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

        <!-- Select Existing Video Modal -->
        <Dialog v-model:open="isSelectModalOpen">
            <DialogContent class="max-h-[80vh] max-w-4xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Sélectionner une vidéo existante</DialogTitle>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="video in allVideos"
                            :key="video.id"
                            class="cursor-pointer rounded-lg border p-3 transition-colors"
                            :class="{
                                'border-blue-500 bg-blue-50': selectedVideoId === video.id,
                                'border-gray-200 hover:border-gray-300': selectedVideoId !== video.id,
                            }"
                            @click="selectedVideoId = video.id"
                        >
                            <div class="mb-2 aspect-video overflow-hidden rounded bg-gray-100">
                                <img
                                    v-if="video.cover_picture"
                                    :src="`/storage/${video.cover_picture.path_small}`"
                                    :alt="video.name"
                                    class="h-full w-full object-cover"
                                />
                                <div v-else class="flex h-full w-full items-center justify-center">
                                    <FileVideo class="h-8 w-8 text-gray-400" />
                                </div>
                            </div>

                            <h4 class="truncate text-sm font-medium">{{ video.name }}</h4>
                            <p :class="getStatusColor(video.status)" class="text-xs font-medium">{{ getStatusLabel(video.status) }}</p>
                        </div>
                    </div>

                    <div v-if="allVideos.length === 0" class="py-8 text-center text-gray-500">
                        <FileVideo class="mx-auto mb-4 h-12 w-12" />
                        <p>Aucune vidéo disponible</p>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="isSelectModalOpen = false"> Annuler </Button>
                    <Button type="button" :disabled="!selectedVideoId || loading" @click="confirmVideoSelection">
                        <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                        Sélectionner
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Upload Video Modal -->
        <Dialog v-model:open="isUploadModalOpen">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Uploader une nouvelle vidéo</DialogTitle>
                </DialogHeader>

                <div class="space-y-4">
                    <!-- File Input -->
                    <div>
                        <Label>Fichier vidéo</Label>
                        <Input type="file" accept="video/*" @change="handleFileSelect" />
                        <p class="mt-1 text-xs text-gray-500">Formats supportés: MP4, AVI, MOV, etc.</p>
                    </div>

                    <!-- Video Name -->
                    <div>
                        <Label>Nom de la vidéo</Label>
                        <Input v-model="newVideoName" placeholder="Nom de la vidéo" />
                    </div>

                    <!-- Cover Picture -->
                    <div>
                        <Label>Image de couverture (optionnelle)</Label>
                        <PictureInput :picture-id="newVideoCoverPictureId" @picture-selected="(id) => (newVideoCoverPictureId = id)" />
                    </div>

                    <!-- Upload Progress -->
                    <div v-if="uploadProgress > 0" class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span>Upload en cours...</span>
                            <span>{{ uploadProgress }}%</span>
                        </div>
                        <div class="h-2 w-full rounded-full bg-gray-200">
                            <div class="h-2 rounded-full bg-blue-600 transition-all" :style="{ width: `${uploadProgress}%` }"></div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" :disabled="loading" @click="isUploadModalOpen = false"> Annuler </Button>
                    <Button type="button" :disabled="!newVideoFile || loading" @click="uploadVideo">
                        <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                        <Upload v-else class="mr-2 h-4 w-4" />
                        Uploader
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
