<script setup lang="ts">
import PictureInput from '@/components/dashboard/PictureInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useRoute } from '@/composables/useRoute';
import type { Video } from '@/types';
import axios from 'axios';
import { Download, Edit, FileVideo, Loader2, Plus, Trash2, Upload } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    videoId?: number;
    contentData?: any;
    locale: 'fr' | 'en';
}

const props = withDefaults(defineProps<Props>(), {
    videoId: undefined,
    contentData: undefined,
});

const emit = defineEmits<{
    (e: 'video-selected', videoId: number): void;
    (e: 'caption-updated', caption: string): void;
}>();

const route = useRoute();

// State
const currentVideo = ref<Video | null>(null);
const allVideos = ref<Video[]>([]);
const loading = ref(false);
const loadingVideos = ref(false);

// Computed réactif pour extraire la caption depuis contentData
const currentCaption = computed(() => {
    if (!props.contentData?.caption_translation_key?.translations) return '';

    const translation = props.contentData.caption_translation_key.translations.find((t: any) => t.locale === props.locale);
    return translation?.text ?? '';
});
const isSelectModalOpen = ref(false);
const isUploadModalOpen = ref(false);
const selectedVideoId = ref<number | undefined>(undefined);
const newVideoFile = ref<File | null>(null);
const newVideoName = ref('');
const newVideoCoverPictureId = ref<number | undefined>(undefined);
const uploadProgress = ref(0);
const downloadingThumbnail = ref(false);

// Load current video if videoId is provided
const loadCurrentVideo = async () => {
    if (!props.videoId) return;

    try {
        const response = await axios.get(route('dashboard.api.videos.show', { video: props.videoId }));
        currentVideo.value = response.data;
    } catch (error) {
        console.error('Erreur lors du chargement de la vidéo:', error);
    }
};

// Load all available videos
const fetchAllVideos = async () => {
    loadingVideos.value = true;
    try {
        const response = await axios.get(route('dashboard.api.videos.index'));
        allVideos.value = response.data;
    } catch (error) {
        console.error('Erreur lors du chargement des vidéos disponibles:', error);
    } finally {
        loadingVideos.value = false;
    }
};

// Select existing video
const selectExistingVideo = async () => {
    isSelectModalOpen.value = true;
    await fetchAllVideos();
};

// Upload new video
const uploadNewVideo = () => {
    isUploadModalOpen.value = true;
};

// Confirm video selection
const confirmVideoSelection = async () => {
    if (!selectedVideoId.value) return;

    // Load the selected video details
    try {
        const response = await axios.get(route('dashboard.api.videos.show', { video: selectedVideoId.value }));
        currentVideo.value = response.data;
        emit('video-selected', selectedVideoId.value);

        isSelectModalOpen.value = false;
        selectedVideoId.value = undefined;
        toast.success('Vidéo sélectionnée avec succès');
    } catch (error) {
        console.error('Erreur lors de la sélection de la vidéo:', error);
        toast.error('Erreur lors de la sélection de la vidéo');
    }
};

// Upload video
const uploadVideo = async () => {
    if (!newVideoFile.value) return;

    loading.value = true;
    uploadProgress.value = 0;

    try {
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

        currentVideo.value = uploadResponse.data;
        emit('video-selected', uploadResponse.data.id);

        resetUploadForm();
        isUploadModalOpen.value = false;
        toast.success('Vidéo uploadée avec succès');
    } catch (error) {
        console.error("Erreur lors de l'upload:", error);
        toast.error("Erreur lors de l'upload de la vidéo");
    } finally {
        loading.value = false;
        uploadProgress.value = 0;
    }
};

// Remove current video
const removeVideo = () => {
    if (!window.confirm('Êtes-vous sûr de vouloir retirer cette vidéo ?')) return;

    currentVideo.value = null;
    emit('video-selected', 0);
    emit('caption-updated', '');
    toast.success('Vidéo retirée');
};

// Update caption
const updateCaption = (caption: string) => {
    emit('caption-updated', caption);
};

// Download thumbnail from BunnyCDN
const downloadThumbnail = async () => {
    if (!currentVideo.value) return;

    downloadingThumbnail.value = true;

    try {
        const response = await axios.post(route('dashboard.api.videos.download-thumbnail', { video: currentVideo.value.id }));

        // Update current video with new cover picture
        currentVideo.value = response.data.video;

        toast.success('Miniature téléchargée et définie comme image de couverture');
    } catch (error) {
        console.error('Erreur lors du téléchargement de la miniature:', error);
        toast.error('Erreur lors du téléchargement de la miniature');
    } finally {
        downloadingThumbnail.value = false;
    }
};

// Reset forms
const resetUploadForm = () => {
    newVideoFile.value = null;
    newVideoName.value = '';
    newVideoCoverPictureId.value = undefined;
};

// File input handler
const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files?.[0]) {
        newVideoFile.value = target.files[0];
        if (!newVideoName.value) {
            newVideoName.value = target.files[0].name;
        }
    }
};

// Load initial data
onMounted(() => {
    void loadCurrentVideo();
});
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
                                    v-if="currentVideo.cover_picture?.path_small"
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
                                    <p class="mt-1 text-xs text-gray-500">Taille: {{ Math.round((currentVideo.file_size || 0) / 1024 / 1024) }} MB</p>
                                </div>

                                <div class="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        :disabled="downloadingThumbnail || currentVideo.status !== 'ready'"
                                        @click="downloadThumbnail"
                                        title="Télécharger la miniature depuis BunnyCDN"
                                    >
                                        <Loader2 v-if="downloadingThumbnail" class="h-4 w-4 animate-spin" />
                                        <Download v-else class="h-4 w-4" />
                                    </Button>
                                    <Button type="button" variant="ghost" size="sm" @click="selectExistingVideo">
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
                        <Label class="text-sm">Description ({{ locale.toUpperCase() }})</Label>
                        <Textarea
                            :value="currentCaption"
                            placeholder="Ajoutez une description pour cette vidéo..."
                            class="min-h-[60px] text-sm"
                            @input="(e: Event) => updateCaption((e.target as HTMLTextAreaElement).value)"
                        />
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- No Video State -->
        <div v-else class="space-y-4">
            <div class="rounded-lg border-2 border-dashed border-gray-300 py-8 text-center">
                <FileVideo class="mx-auto mb-4 h-12 w-12 text-gray-400" />
                <p class="mb-4 text-sm text-gray-600">Aucune vidéo sélectionnée</p>

                <div class="flex justify-center gap-2">
                    <Button type="button" variant="outline" size="sm" @click="selectExistingVideo">
                        <Plus class="mr-2 h-4 w-4" />
                        Sélectionner une vidéo
                    </Button>
                    <Button type="button" variant="outline" size="sm" @click="uploadNewVideo">
                        <Upload class="mr-2 h-4 w-4" />
                        Uploader une vidéo
                    </Button>
                </div>
            </div>
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
                                    v-if="video.cover_picture?.path_small"
                                    :src="`/storage/${video.cover_picture.path_small}`"
                                    :alt="video.name"
                                    class="h-full w-full object-cover"
                                />
                                <div v-else class="flex h-full w-full items-center justify-center">
                                    <FileVideo class="h-8 w-8 text-gray-400" />
                                </div>
                            </div>

                            <h4 class="truncate text-sm font-medium">{{ video.name }}</h4>
                            <p class="text-xs text-gray-500">{{ Math.round((video.file_size || 0) / 1024 / 1024) }} MB</p>
                        </div>
                    </div>

                    <div v-if="loadingVideos" class="py-8 text-center text-gray-500">
                        <Loader2 class="mx-auto mb-4 h-12 w-12 animate-spin" />
                        <p>Chargement des vidéos...</p>
                    </div>
                    <div v-else-if="allVideos.length === 0" class="py-8 text-center text-gray-500">
                        <FileVideo class="mx-auto mb-4 h-12 w-12" />
                        <p>Aucune vidéo disponible</p>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="isSelectModalOpen = false"> Annuler </Button>
                    <Button type="button" :disabled="!selectedVideoId" @click="confirmVideoSelection"> Sélectionner </Button>
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
