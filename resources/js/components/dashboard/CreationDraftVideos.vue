<script setup lang="ts">
import HeadingSmall from '@/components/dashboard/HeadingSmall.vue';
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
import { Video } from '@/types';
import axios from 'axios';
import { Edit, FileVideo, ImageDown, Loader2, Plus, Trash2, Upload } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';

const props = defineProps<{
    creationDraftId: number | null;
}>();

const videos = ref<Video[]>([]);
const allVideos = ref<Video[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);

// Modales
const isSelectModalOpen = ref(false);
const isUploadModalOpen = ref(false);
const isEditModalOpen = ref(false);
const isDetachDialogOpen = ref(false);
const editingVideo = ref<Video | null>(null);
const videoToDetach = ref<Video | null>(null);

// Ref to VideoManager for helpers
// eslint-disable-next-line @typescript-eslint/no-redundant-type-constituents
const videoManager = ref<InstanceType<typeof VideoManager> | null>(null);

const fetchVideos = async () => {
    if (!props.creationDraftId) return;

    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get(
            route('dashboard.api.creation-drafts.videos', {
                creation_draft: props.creationDraftId,
            }),
        );
        videos.value = response.data;
    } catch (err) {
        error.value = 'Erreur lors du chargement des vidéos';
        console.error(err);
    } finally {
        loading.value = false;
    }
};

const fetchAllVideos = async () => {
    try {
        const response = await axios.get(route('dashboard.api.videos.index'));
        allVideos.value = response.data;
    } catch (err) {
        console.error('Erreur lors du chargement des vidéos disponibles:', err);
    }
};

const confirmDetachVideo = (video: Video) => {
    videoToDetach.value = video;
    isDetachDialogOpen.value = true;
};

const detachVideo = async () => {
    if (!videoToDetach.value) return;

    loading.value = true;
    error.value = null;
    isDetachDialogOpen.value = false;

    try {
        await axios.post(
            route('dashboard.api.creation-drafts.detach-video', {
                creation_draft: props.creationDraftId,
            }),
            {
                video_id: videoToDetach.value.id,
            },
        );

        await fetchVideos();
        videoToDetach.value = null;
    } catch (err) {
        error.value = 'Erreur lors de la suppression de la vidéo';
        console.error(err);
    } finally {
        loading.value = false;
    }
};

const openEditModal = (video: Video) => {
    editingVideo.value = video;
    isEditModalOpen.value = true;
};

// VideoManager event handlers

const handleVideoUploaded = async (video: Video) => {
    // Attach the uploaded video to the creation
    if (!props.creationDraftId) return;

    try {
        await axios.post(
            route('dashboard.api.creation-drafts.attach-video', {
                creation_draft: props.creationDraftId,
            }),
            {
                video_id: video.id,
            },
        );

        await fetchVideos();
    } catch (err) {
        error.value = "Erreur lors de l'ajout de la vidéo";
        console.error(err);
    }
};

const handleVideoSelected = async (videoId: number) => {
    // Attach the selected video to the creation
    if (!props.creationDraftId) return;

    try {
        await axios.post(
            route('dashboard.api.creation-drafts.attach-video', {
                creation_draft: props.creationDraftId,
            }),
            {
                video_id: videoId,
            },
        );

        await fetchVideos();
    } catch (err) {
        error.value = "Erreur lors de l'ajout de la vidéo";
        console.error(err);
    }
};

const handleVideoUpdated = async () => {
    await fetchVideos();
    await fetchAllVideos();
    editingVideo.value = null;
};

const handleThumbnailDownloaded = async () => {
    await fetchVideos();
    await fetchAllVideos();
};

// Open modals
const openSelectModal = async () => {
    await fetchAllVideos();
    isSelectModalOpen.value = true;
};

const openUploadModal = () => {
    isUploadModalOpen.value = true;
};

// Get attached video IDs to exclude from selection
const getExcludedVideoIds = () => {
    return videos.value.map((v) => v.id);
};

onMounted(() => {
    if (props.creationDraftId) {
        void fetchVideos();
    }
    void fetchAllVideos();
});

watch(
    () => props.creationDraftId,
    async (newDraftId, oldDraftId) => {
        if (newDraftId && newDraftId !== oldDraftId) {
            await fetchVideos();
        }
    },
);
</script>

<template>
    <div class="space-y-6">
        <HeadingSmall title="Vidéos" description="Ajoutez des vidéos pour présenter votre création en action." />

        <div v-if="error" class="bg-destructive/10 text-destructive mb-4 rounded-md p-4 text-sm">
            {{ error }}
        </div>

        <div v-if="!props.creationDraftId" class="bg-muted text-muted-foreground rounded-md p-4 text-sm">
            Veuillez d'abord enregistrer le brouillon pour pouvoir ajouter des vidéos.
        </div>

        <div v-else>
            <div class="mb-4 flex gap-2">
                <Button variant="outline" size="sm" @click="openSelectModal">
                    <Plus class="mr-2 h-4 w-4" />
                    Sélectionner une vidéo
                </Button>
                <Button variant="outline" size="sm" @click="openUploadModal">
                    <Upload class="mr-2 h-4 w-4" />
                    Uploader une vidéo
                </Button>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card v-for="video in videos" :key="video.id" class="gap-0 overflow-hidden py-0">
                    <div class="bg-muted relative aspect-video">
                        <img
                            v-if="video.cover_picture"
                            :src="`/storage/${video.cover_picture.path_original}`"
                            :alt="video.name"
                            class="h-full w-full object-cover"
                        />
                        <div v-else class="flex h-full w-full items-center justify-center">
                            <FileVideo class="text-muted-foreground h-12 w-12" />
                        </div>
                        <div class="absolute right-2 bottom-2 rounded bg-black/70 px-2 py-1 text-xs text-white">Vidéo</div>
                    </div>
                    <CardContent class="p-4">
                        <div class="flex items-start justify-between">
                            <div class="min-w-0 flex-1">
                                <h3 class="truncate text-sm font-medium">{{ video.name }}</h3>
                                <div class="mt-1 flex items-center gap-2 text-xs">
                                    <span class="text-muted-foreground">ID Bunny: {{ video.bunny_video_id }}</span>
                                    <span v-if="videoManager" :class="videoManager.getStatusColor(video.status)" class="font-medium">
                                        • {{ videoManager.getStatusLabel(video.status) }}
                                    </span>
                                    <span class="text-muted-foreground"> • {{ video.visibility === 'public' ? 'Publique' : 'Privée' }} </span>
                                </div>
                            </div>
                            <div class="ml-2 flex flex-shrink-0 space-x-1">
                                <Button
                                    v-if="video.status === 'ready' && videoManager"
                                    variant="ghost"
                                    size="icon"
                                    title="Télécharger miniature comme couverture"
                                    :disabled="loading"
                                    @click.stop="videoManager.downloadThumbnail(video)"
                                >
                                    <ImageDown class="h-4 w-4" />
                                </Button>
                                <Button variant="ghost" size="icon" title="Modifier la vidéo" @click.stop="openEditModal(video)">
                                    <Edit class="h-4 w-4" />
                                </Button>
                                <Button variant="ghost" size="icon" title="Retirer de la création" @click.stop="confirmDetachVideo(video)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div v-if="videos.length === 0 && !loading" class="text-muted-foreground py-8 text-center">
                <p>Aucune vidéo ajoutée</p>
                <div class="mt-4 flex justify-center gap-2">
                    <Button variant="outline" @click="openSelectModal"> Sélectionner une vidéo </Button>
                    <Button variant="outline" @click="openUploadModal"> Uploader une vidéo </Button>
                </div>
            </div>

            <div v-if="loading" class="flex justify-center py-8">
                <Loader2 class="text-primary h-8 w-8 animate-spin" />
            </div>
        </div>

        <!-- VideoManager with all modals -->
        <VideoManager
            ref="videoManager"
            v-model:show-select-modal="isSelectModalOpen"
            v-model:show-upload-modal="isUploadModalOpen"
            v-model:show-edit-modal="isEditModalOpen"
            :available-videos="allVideos"
            :exclude-video-ids="getExcludedVideoIds()"
            :editing-video="editingVideo"
            :allow-visibility-edit="true"
            :allow-thumbnail-download="true"
            @video-uploaded="handleVideoUploaded"
            @video-selected="handleVideoSelected"
            @video-updated="handleVideoUpdated"
            @thumbnail-downloaded="handleThumbnailDownloaded"
        />

        <!-- Detach Video Confirmation Dialog -->
        <AlertDialog v-model:open="isDetachDialogOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Êtes-vous sûr de vouloir retirer cette vidéo de la création ?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Cette action retirera la vidéo de cette création. La vidéo ne sera pas supprimée et pourra être réutilisée.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Annuler</AlertDialogCancel>
                    <AlertDialogAction @click="detachVideo">
                        <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                        Retirer
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
