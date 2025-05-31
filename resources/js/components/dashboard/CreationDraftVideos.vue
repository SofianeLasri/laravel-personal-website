<script setup lang="ts">
import HeadingSmall from '@/components/dashboard/HeadingSmall.vue';
import PictureInput from '@/components/dashboard/PictureInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Video } from '@/types';
import axios from 'axios';
import { FileVideo, Loader2, Plus, Trash2, Upload } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';

const props = defineProps<{
    creationDraftId: number | null;
}>();

const videos = ref<Video[]>([]);
const allVideos = ref<Video[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const isSelectModalOpen = ref(false);
const isUploadModalOpen = ref(false);
const selectedVideoId = ref<number | undefined>(undefined);
const newVideoFile = ref<File | null>(null);
const newVideoName = ref('');
const newVideoCoverPictureId = ref<number | undefined>(undefined);
const uploadProgress = ref(0);

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

const attachVideo = async () => {
    if (!selectedVideoId.value || !props.creationDraftId) {
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        await axios.post(
            route('dashboard.api.creation-drafts.attach-video', {
                creation_draft: props.creationDraftId,
            }),
            {
                video_id: selectedVideoId.value,
            },
        );

        await fetchVideos();
        resetSelectForm();
        isSelectModalOpen.value = false;
    } catch (err) {
        error.value = "Erreur lors de l'ajout de la vidéo";
        console.error(err);
    } finally {
        loading.value = false;
    }
};

const uploadVideo = async () => {
    if (!newVideoFile.value || !newVideoCoverPictureId.value || !props.creationDraftId) {
        return;
    }

    loading.value = true;
    error.value = null;
    uploadProgress.value = 0;

    try {
        const formData = new FormData();
        formData.append('video', newVideoFile.value);
        formData.append('name', newVideoName.value || newVideoFile.value.name);
        formData.append('cover_picture_id', newVideoCoverPictureId.value.toString());

        // Upload de la vidéo
        const uploadResponse = await axios.post(route('dashboard.api.videos.store'), formData, {
            onUploadProgress: (progressEvent) => {
                if (progressEvent.total) {
                    uploadProgress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                }
            },
        });

        // Attacher la vidéo à la création
        await axios.post(
            route('dashboard.api.creation-drafts.attach-video', {
                creation_draft: props.creationDraftId,
            }),
            {
                video_id: uploadResponse.data.id,
            },
        );

        await fetchVideos();
        resetUploadForm();
        isUploadModalOpen.value = false;
    } catch (err) {
        error.value = "Erreur lors de l'upload de la vidéo";
        console.error(err);
    } finally {
        loading.value = false;
        uploadProgress.value = 0;
    }
};

const detachVideo = async (video: Video) => {
    if (!confirm('Êtes-vous sûr de vouloir retirer cette vidéo de la création ?')) {
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        await axios.post(
            route('dashboard.api.creation-drafts.detach-video', {
                creation_draft: props.creationDraftId,
            }),
            {
                video_id: video.id,
            },
        );

        await fetchVideos();
    } catch (err) {
        error.value = 'Erreur lors de la suppression de la vidéo';
        console.error(err);
    } finally {
        loading.value = false;
    }
};

const resetSelectForm = () => {
    selectedVideoId.value = undefined;
};

const resetUploadForm = () => {
    newVideoFile.value = null;
    newVideoName.value = '';
    newVideoCoverPictureId.value = undefined;
};

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        newVideoFile.value = target.files[0];
        if (!newVideoName.value) {
            newVideoName.value = target.files[0].name.replace(/\.[^/.]+$/, '');
        }
    }
};

const getAvailableVideos = () => {
    const attachedVideoIds = videos.value.map((v) => v.id);
    return allVideos.value.filter((v) => !attachedVideoIds.includes(v.id));
};

const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

onMounted(() => {
    if (props.creationDraftId) {
        fetchVideos();
    }
    fetchAllVideos();
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
                <Button @click="isSelectModalOpen = true" variant="outline" size="sm">
                    <Plus class="mr-2 h-4 w-4" />
                    Sélectionner une vidéo
                </Button>
                <Button @click="isUploadModalOpen = true" variant="outline" size="sm">
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
                                <p class="text-muted-foreground mt-1 text-xs">ID Bunny: {{ video.bunny_video_id }}</p>
                            </div>
                            <div class="ml-2 flex flex-shrink-0 space-x-1">
                                <Button variant="ghost" size="icon" @click.stop="detachVideo(video)" title="Retirer de la création">
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
                    <Button variant="outline" @click="isSelectModalOpen = true"> Sélectionner une vidéo </Button>
                    <Button variant="outline" @click="isUploadModalOpen = true"> Uploader une vidéo </Button>
                </div>
            </div>

            <div v-if="loading" class="flex justify-center py-8">
                <Loader2 class="text-primary h-8 w-8 animate-spin" />
            </div>
        </div>

        <!-- Modal de sélection -->
        <Dialog v-model:open="isSelectModalOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Sélectionner une vidéo</DialogTitle>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label>Vidéo disponible</Label>
                        <Select v-model="selectedVideoId">
                            <SelectTrigger>
                                <SelectValue placeholder="Choisissez une vidéo" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="video in getAvailableVideos()" :key="video.id" :value="video.id.toString()">
                                    {{ video.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="getAvailableVideos().length === 0" class="text-muted-foreground text-xs">
                            Toutes les vidéos disponibles sont déjà attachées à cette création.
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="isSelectModalOpen = false" :disabled="loading">Annuler</Button>
                    <Button :disabled="!selectedVideoId || loading" @click="attachVideo">
                        <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                        Ajouter
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
                        <Input type="file" accept="video/*" @change="handleFileSelect" :disabled="loading" />
                        <p class="text-muted-foreground text-xs">Formats supportés: MP4, AVI, MOV, WMV, FLV, WebM, MKV (max 500MB)</p>
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
                        <Label>Image de couverture</Label>
                        <PictureInput v-model="newVideoCoverPictureId" :disabled="loading" />
                    </div>

                    <div v-if="uploadProgress > 0" class="space-y-2">
                        <Label>Progression de l'upload</Label>
                        <div class="h-2 w-full rounded-full bg-gray-200">
                            <div class="h-2 rounded-full bg-blue-600 transition-all duration-300" :style="{ width: uploadProgress + '%' }"></div>
                        </div>
                        <p class="text-muted-foreground text-sm">{{ uploadProgress }}%</p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="isUploadModalOpen = false" :disabled="loading">Annuler</Button>
                    <Button :disabled="!newVideoFile || !newVideoCoverPictureId || loading" @click="uploadVideo">
                        <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                        <Upload v-else class="mr-2 h-4 w-4" />
                        Uploader et ajouter
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
