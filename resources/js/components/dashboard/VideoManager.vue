<script setup lang="ts">
import PictureInput from '@/components/dashboard/PictureInput.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useRoute } from '@/composables/useRoute';
import type { Video } from '@/types';
import axios from 'axios';
import { Download, FileVideo, ImageDown, Loader2, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

/**
 * VideoManager - Composant modulaire et réutilisable pour gérer les vidéos
 *
 * Ce composant unifie toute la logique de gestion des vidéos (upload, édition, sélection, import)
 * et peut être configuré via props pour s'adapter à différents cas d'usage.
 */

interface Props {
    // Modales à afficher
    showSelectModal?: boolean;
    showUploadModal?: boolean;
    showEditModal?: boolean;
    showImportModal?: boolean;

    // Options de fonctionnalités
    allowVisibilityEdit?: boolean;
    allowThumbnailDownload?: boolean;

    // Données pour la sélection
    availableVideos?: Video[];
    excludeVideoIds?: number[];

    // Vidéo en cours d'édition
    editingVideo?: Video | null;
}

const props = withDefaults(defineProps<Props>(), {
    showSelectModal: false,
    showUploadModal: false,
    showEditModal: false,
    showImportModal: false,
    allowVisibilityEdit: false,
    allowThumbnailDownload: true,
    availableVideos: () => [],
    excludeVideoIds: () => [],
    editingVideo: null,
});

const emit = defineEmits<{
    // Événements des modales
    (e: 'update:showSelectModal', value: boolean): void;
    (e: 'update:showUploadModal', value: boolean): void;
    (e: 'update:showEditModal', value: boolean): void;
    (e: 'update:showImportModal', value: boolean): void;

    // Événements des actions
    (e: 'video-uploaded', video: Video): void;
    (e: 'video-selected', videoId: number): void;
    (e: 'video-updated', video: Video): void;
    (e: 'video-imported', video: Video): void;
    (e: 'thumbnail-downloaded', video: Video): void;
}>();

const route = useRoute();

// State
const loading = ref(false);
const uploadProgress = ref(0);
const syncingStatus = ref(false);

// Upload form
const newVideoFile = ref<File | null>(null);
const newVideoName = ref('');
const newVideoCoverPictureId = ref<number | undefined>(undefined);

// Edit form
const editVideoName = ref('');
const editVideoCoverPictureId = ref<number | undefined>(undefined);
const editVideoVisibility = ref<'private' | 'public'>('private');

// Synced video data (including cover_picture relationship and bunny_data)
const syncedVideoData = ref<Video & { bunny_data?: any; cover_picture?: any } | null>(null);

// Select form
const selectedVideoId = ref<number | undefined>(undefined);

// Import form
const importBunnyVideoId = ref('');
const importDownloadThumbnail = ref(true);

// Computed
const isSelectModalOpen = computed({
    get: () => props.showSelectModal,
    set: (value) => emit('update:showSelectModal', value),
});

const isUploadModalOpen = computed({
    get: () => props.showUploadModal,
    set: (value) => emit('update:showUploadModal', value),
});

const isEditModalOpen = computed({
    get: () => props.showEditModal,
    set: (value) => emit('update:showEditModal', value),
});

const isImportModalOpen = computed({
    get: () => props.showImportModal,
    set: (value) => emit('update:showImportModal', value),
});

const filteredAvailableVideos = computed(() => {
    return props.availableVideos.filter(v => !props.excludeVideoIds.includes(v.id));
});

// Use synced video data when available, otherwise fallback to editingVideo from props
const currentEditingVideo = computed(() => {
    return syncedVideoData.value || props.editingVideo;
});

// Actions

/**
 * Upload a new video
 */
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

        const response = await axios.post(route('dashboard.api.videos.store'), formData, {
            onUploadProgress: (progressEvent) => {
                if (progressEvent.total) {
                    uploadProgress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                }
            },
        });

        emit('video-uploaded', response.data);
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

/**
 * Select an existing video
 */
const selectVideo = async () => {
    if (!selectedVideoId.value) return;

    emit('video-selected', selectedVideoId.value);
    resetSelectForm();
    isSelectModalOpen.value = false;
};

/**
 * Update video details
 */
const updateVideo = async () => {
    if (!props.editingVideo) return;

    loading.value = true;

    try {
        const updateData: { name?: string; cover_picture_id?: number; visibility?: string } = {};

        if (editVideoName.value) {
            updateData.name = editVideoName.value;
        }

        if (editVideoCoverPictureId.value !== undefined) {
            updateData.cover_picture_id = editVideoCoverPictureId.value;
        }

        if (props.allowVisibilityEdit) {
            updateData.visibility = editVideoVisibility.value;
        }

        const response = await axios.put(
            route('dashboard.api.videos.update', { video: props.editingVideo.id }),
            updateData
        );

        emit('video-updated', response.data);
        resetEditForm();
        isEditModalOpen.value = false;
        toast.success('Vidéo mise à jour avec succès');
    } catch (error) {
        console.error('Erreur lors de la mise à jour:', error);
        toast.error('Erreur lors de la mise à jour');
    } finally {
        loading.value = false;
    }
};

/**
 * Import video from Bunny Stream
 */
const importFromBunny = async () => {
    if (!importBunnyVideoId.value.trim()) {
        toast.error('Veuillez entrer un ID de vidéo Bunny Stream');
        return;
    }

    loading.value = true;

    try {
        const response = await axios.post(route('dashboard.api.videos.import-from-bunny'), {
            bunny_video_id: importBunnyVideoId.value.trim(),
            download_thumbnail: importDownloadThumbnail.value,
        });

        emit('video-imported', response.data.video);
        resetImportForm();
        isImportModalOpen.value = false;
        toast.success(response.data.message || 'Vidéo importée avec succès');
    } catch (error: any) {
        console.error("Erreur lors de l'import:", error);
        const message = error.response?.data?.message || "Erreur lors de l'import de la vidéo";
        toast.error(message);
    } finally {
        loading.value = false;
    }
};

/**
 * Download thumbnail from Bunny CDN
 */
const downloadThumbnail = async (video: Video) => {
    if (!props.allowThumbnailDownload) return;

    if (!confirm('Télécharger la miniature Bunny Stream comme image de couverture ?')) {
        return;
    }

    loading.value = true;

    try {
        await axios.post(
            route('dashboard.api.videos.download-thumbnail', { video: video.id })
        );

        // Get updated video data
        const response = await axios.get(route('dashboard.api.videos.show', { video: video.id }));

        // Update synced data
        syncedVideoData.value = response.data;

        // Re-initialize form with new data
        initializeEditForm();

        emit('thumbnail-downloaded', response.data);
        toast.success('Miniature téléchargée et définie comme image de couverture');
    } catch (error) {
        console.error('Erreur lors du téléchargement de la miniature:', error);
        toast.error('Erreur lors du téléchargement de la miniature');
    } finally {
        loading.value = false;
    }
};

/**
 * Sync video status from BunnyCDN
 * This method fetches the latest video data including Bunny Stream status
 */
const syncVideoStatus = async () => {
    if (!props.editingVideo) return;

    syncingStatus.value = true;

    try {
        const response = await axios.get(route('dashboard.api.videos.show', { video: props.editingVideo.id }));

        // Store the synced data (includes bunny_data and cover_picture)
        syncedVideoData.value = response.data;

        // Re-initialize the form with updated data
        initializeEditForm();

        console.log('Video status synced from BunnyCDN', response.data);
    } catch (error) {
        console.error('Erreur lors de la synchronisation du statut:', error);
        toast.error('Erreur lors de la synchronisation du statut de la vidéo');
    } finally {
        syncingStatus.value = false;
    }
};

// Reset forms

const resetUploadForm = () => {
    newVideoFile.value = null;
    newVideoName.value = '';
    newVideoCoverPictureId.value = undefined;
};

const resetSelectForm = () => {
    selectedVideoId.value = undefined;
};

const resetEditForm = () => {
    editVideoName.value = '';
    editVideoCoverPictureId.value = undefined;
    editVideoVisibility.value = 'private';
};

const resetImportForm = () => {
    importBunnyVideoId.value = '';
    importDownloadThumbnail.value = true;
};

// File handlers

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files?.[0]) {
        newVideoFile.value = target.files[0];
        if (!newVideoName.value) {
            newVideoName.value = target.files[0].name.replace(/\.[^/.]+$/, '');
        }
    }
};

// Helpers

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

const canSetPublic = (status: string): boolean => {
    return status === 'ready';
};

const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`;
};

// Initialize edit form when editing video changes
const initializeEditForm = () => {
    const videoData = currentEditingVideo.value;
    if (videoData) {
        editVideoName.value = videoData.name;
        editVideoCoverPictureId.value = videoData.cover_picture_id;
        editVideoVisibility.value = videoData.visibility;
    }
};

// Watch for changes in editingVideo and modal state
import { watch } from 'vue';
watch(
    [() => props.editingVideo, isEditModalOpen],
    async ([newEditingVideo, newModalOpen], [oldEditingVideo, oldModalOpen]) => {
        // When modal opens with an editing video
        if (newEditingVideo && newModalOpen && !oldModalOpen) {
            // Sync status from BunnyCDN
            await syncVideoStatus();
        } else if (newEditingVideo && newModalOpen) {
            // Just initialize if already open
            initializeEditForm();
        }

        // Reset synced data when modal closes
        if (!newModalOpen && oldModalOpen) {
            syncedVideoData.value = null;
        }
    },
    { immediate: false }
);

// Expose helpers for parent components
defineExpose({
    getStatusLabel,
    getStatusColor,
    canSetPublic,
    formatFileSize,
    downloadThumbnail,
    syncVideoStatus,
});
</script>

<template>
    <div>
        <!-- Modal de sélection -->
        <Dialog v-model:open="isSelectModalOpen">
            <DialogContent class="max-h-[80vh] max-w-4xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Sélectionner une vidéo</DialogTitle>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div v-if="filteredAvailableVideos.length > 0" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="video in filteredAvailableVideos"
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
                                    :src="`/storage/${video.cover_picture.path_original}`"
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

                    <div v-else class="py-8 text-center text-gray-500">
                        <FileVideo class="mx-auto mb-4 h-12 w-12" />
                        <p>Aucune vidéo disponible</p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" :disabled="loading" @click="isSelectModalOpen = false">Annuler</Button>
                    <Button :disabled="!selectedVideoId || loading" @click="selectVideo">
                        <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                        Sélectionner
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Modal d'upload -->
        <Dialog v-model:open="isUploadModalOpen">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Uploader une nouvelle vidéo</DialogTitle>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label>Fichier vidéo</Label>
                        <Input type="file" accept="video/*" :disabled="loading" @change="handleFileSelect" />
                        <p class="text-muted-foreground text-xs">Formats supportés: MP4, AVI, MOV, WMV, FLV, WebM, MKV (max 2000MB)</p>
                        <div v-if="newVideoFile" class="text-sm">
                            <p><strong>Fichier:</strong> {{ newVideoFile.name }}</p>
                            <p><strong>Taille:</strong> {{ formatFileSize(newVideoFile.size) }}</p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label>Titre de la vidéo</Label>
                        <Input v-model="newVideoName" placeholder="Titre de la vidéo" :disabled="loading" />
                    </div>

                    <div class="space-y-2">
                        <Label>Image de couverture (optionnelle)</Label>
                        <PictureInput v-model="newVideoCoverPictureId" :disabled="loading" />
                    </div>

                    <div v-if="uploadProgress > 0" class="space-y-2">
                        <Label>Progression de l'upload</Label>
                        <div class="h-2 w-full rounded-full bg-gray-200">
                            <div class="h-2 rounded-full bg-blue-600 transition-all duration-300" :style="{ width: `${uploadProgress}%` }"></div>
                        </div>
                        <p class="text-muted-foreground text-sm">{{ uploadProgress }}%</p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" :disabled="loading" @click="isUploadModalOpen = false">Annuler</Button>
                    <Button :disabled="!newVideoFile || loading" @click="uploadVideo">
                        <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                        <Upload v-else class="mr-2 h-4 w-4" />
                        Uploader
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Modal d'édition -->
        <Dialog v-model:open="isEditModalOpen" @update:open="(open) => open && initializeEditForm()">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Modifier la vidéo</DialogTitle>
                    <div v-if="syncingStatus" class="mt-2 flex items-center gap-2 text-sm text-blue-600">
                        <Loader2 class="h-4 w-4 animate-spin" />
                        Synchronisation avec BunnyCDN...
                    </div>
                </DialogHeader>

                <div v-if="currentEditingVideo" class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label>Titre de la vidéo</Label>
                        <Input v-model="editVideoName" placeholder="Titre de la vidéo" :disabled="loading" />
                    </div>

                    <div class="space-y-2">
                        <Label>Image de couverture</Label>

                        <!-- Aperçu de l'image de couverture actuelle -->
                        <div v-if="currentEditingVideo.cover_picture" class="mb-3 rounded-lg border p-3 bg-gray-50 dark:bg-gray-800">
                            <p class="mb-2 text-sm font-medium">Image actuelle:</p>
                            <div class="aspect-video w-full max-w-xs overflow-hidden rounded-lg bg-gray-100">
                                <img
                                    :src="`/storage/${currentEditingVideo.cover_picture.path_original}`"
                                    :alt="currentEditingVideo.name"
                                    class="h-full w-full object-cover"
                                />
                            </div>
                        </div>

                        <PictureInput v-model="editVideoCoverPictureId" :disabled="loading" />
                        <p class="text-muted-foreground text-xs">L'image de couverture sera utilisée comme miniature pour la vidéo</p>

                        <!-- Bouton de téléchargement de miniature BunnyCDN -->
                        <Button
                            v-if="allowThumbnailDownload && currentEditingVideo.status === 'ready'"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="w-full"
                            :disabled="loading"
                            @click="downloadThumbnail(currentEditingVideo)"
                        >
                            <ImageDown class="mr-2 h-4 w-4" />
                            Télécharger la miniature BunnyCDN
                        </Button>
                    </div>

                    <div v-if="allowVisibilityEdit" class="space-y-2">
                        <Label>Visibilité</Label>
                        <Select v-model="editVideoVisibility" :disabled="loading">
                            <SelectTrigger>
                                <SelectValue placeholder="Sélectionner la visibilité" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="private">Privée</SelectItem>
                                <SelectItem value="public" :disabled="!canSetPublic(currentEditingVideo.status)"> Publique </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="!canSetPublic(currentEditingVideo.status)" class="text-muted-foreground text-xs">
                            La vidéo doit être entièrement transcodée pour être rendue publique
                        </p>
                        <p class="text-muted-foreground text-xs">
                            <strong>Visibilité actuelle:</strong>
                            {{ currentEditingVideo.visibility === 'public' ? 'Publique' : 'Privée' }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label>Statut et informations</Label>
                        <div class="space-y-1 text-sm">
                            <p>
                                <strong>Statut:</strong>
                                <span :class="getStatusColor(currentEditingVideo.status)" class="font-medium">
                                    {{ getStatusLabel(currentEditingVideo.status) }}
                                </span>
                            </p>
                            <p v-if="syncedVideoData?.bunny_data">
                                <strong>Statut BunnyCDN:</strong>
                                <span class="font-medium">
                                    {{ syncedVideoData.bunny_data.is_ready ? 'Prêt' : 'En traitement' }}
                                </span>
                                <span v-if="syncedVideoData.bunny_data.duration" class="text-muted-foreground ml-2">
                                    (Durée: {{ Math.floor(syncedVideoData.bunny_data.duration / 60) }}:{{ String(syncedVideoData.bunny_data.duration % 60).padStart(2, '0') }})
                                </span>
                            </p>
                            <p><strong>ID Bunny:</strong> {{ currentEditingVideo.bunny_video_id }}</p>
                            <p><strong>Créée le:</strong> {{ new Date(currentEditingVideo.created_at).toLocaleDateString('fr-FR') }}</p>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" :disabled="loading" @click="isEditModalOpen = false">Annuler</Button>
                    <Button :disabled="!editVideoName.trim() || loading" @click="updateVideo">
                        <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                        Mettre à jour
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Modal d'import Bunny Stream -->
        <Dialog v-model:open="isImportModalOpen">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Importer une vidéo depuis Bunny Stream</DialogTitle>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-900 dark:bg-blue-900/20 dark:text-blue-100">
                        <p class="font-medium">Information</p>
                        <p class="mt-1">Cette fonctionnalité permet d'importer des vidéos déjà hébergées sur Bunny Stream sans avoir à les télécharger et re-uploader.</p>
                    </div>

                    <div class="space-y-2">
                        <Label>ID de la vidéo Bunny Stream</Label>
                        <Input
                            v-model="importBunnyVideoId"
                            placeholder="ex: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                            :disabled="loading"
                        />
                        <p class="text-muted-foreground text-xs">
                            Vous pouvez trouver l'ID de la vidéo dans le tableau de bord Bunny Stream
                        </p>
                    </div>

                    <div class="flex items-center space-x-2">
                        <input
                            id="download-thumbnail"
                            v-model="importDownloadThumbnail"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            :disabled="loading"
                        />
                        <label for="download-thumbnail" class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                            Télécharger la miniature comme image de couverture
                        </label>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" :disabled="loading" @click="isImportModalOpen = false">Annuler</Button>
                    <Button :disabled="!importBunnyVideoId.trim() || loading" @click="importFromBunny">
                        <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                        <Download v-else class="mr-2 h-4 w-4" />
                        Importer
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
