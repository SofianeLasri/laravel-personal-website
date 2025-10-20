<script setup lang="ts">
import Heading from '@/components/dashboard/Heading.vue';
import PictureInput from '@/components/dashboard/PictureInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useRoute } from '@/composables/useRoute';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Video } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { Download, Edit, ExternalLink, FileVideo, Loader2, Plus, Trash2, Upload } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

interface VideoUsage {
    id: number;
    type: 'creation' | 'blog_post';
    title: string;
    slug?: string;
    url: string;
}

interface VideoWithUsage extends Video {
    usages: VideoUsage[];
}

interface Props {
    videos: VideoWithUsage[];
}

const props = defineProps<Props>();
const route = useRoute();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Vidéos',
        href: route('dashboard.videos.index', undefined, false),
    },
];

// State
const videos = ref<VideoWithUsage[]>([...props.videos]);
const loading = ref(false);
const isUploadModalOpen = ref(false);
const isEditModalOpen = ref(false);
const isImportModalOpen = ref(false);
const newVideoFile = ref<File | null>(null);
const newVideoName = ref('');
const newVideoCoverPictureId = ref<number | undefined>(undefined);
const editingVideo = ref<VideoWithUsage | null>(null);
const editVideoName = ref('');
const editVideoCoverPictureId = ref<number | undefined>(undefined);
const uploadProgress = ref(0);
const importBunnyVideoId = ref('');
const importDownloadThumbnail = ref(true);

// Upload new video
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

        // Add new video to list with empty usages
        videos.value.unshift({
            ...response.data,
            usages: [],
        });

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

// Edit video
const openEditModal = (video: VideoWithUsage) => {
    editingVideo.value = video;
    editVideoName.value = video.name;
    editVideoCoverPictureId.value = video.cover_picture?.id;
    isEditModalOpen.value = true;
};

const updateVideo = async () => {
    if (!editingVideo.value) return;

    loading.value = true;

    try {
        const updateData: { name: string; cover_picture_id?: number } = {
            name: editVideoName.value,
        };

        if (editVideoCoverPictureId.value) {
            updateData.cover_picture_id = editVideoCoverPictureId.value;
        }

        const response = await axios.put(route('dashboard.api.videos.update', { video: editingVideo.value.id }), updateData);

        // Update video in list
        const index = videos.value.findIndex((v) => v.id === editingVideo.value?.id);
        if (index !== -1) {
            videos.value[index] = {
                ...response.data,
                usages: videos.value[index].usages, // Preserve usages
            };
        }

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

// Delete video
const deleteVideo = async (video: VideoWithUsage) => {
    if (video.usages.length > 0) {
        toast.error('Impossible de supprimer une vidéo utilisée dans des contenus');
        return;
    }

    if (!window.confirm(`Êtes-vous sûr de vouloir supprimer la vidéo "${video.name}" ?`)) {
        return;
    }

    try {
        await axios.delete(route('dashboard.api.videos.destroy', { video: video.id }));

        // Remove from list
        const index = videos.value.findIndex((v) => v.id === video.id);
        if (index !== -1) {
            videos.value.splice(index, 1);
        }

        toast.success('Vidéo supprimée avec succès');
    } catch (error) {
        console.error('Erreur lors de la suppression:', error);
        toast.error('Erreur lors de la suppression');
    }
};

// Refresh video usages
const refreshVideos = () => {
    try {
        router.reload({ only: ['videos'] });
    } catch (error) {
        console.error('Erreur lors du rechargement:', error);
    }
};

// Reset forms
const resetUploadForm = () => {
    newVideoFile.value = null;
    newVideoName.value = '';
    newVideoCoverPictureId.value = undefined;
};

const resetEditForm = () => {
    editingVideo.value = null;
    editVideoName.value = '';
    editVideoCoverPictureId.value = undefined;
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

// Format file size
const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`;
};

// Format date
const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('fr-FR');
};

// Get usage type label
const getUsageTypeLabel = (usage: VideoUsage): string => {
    return usage.type === 'creation' ? 'Création' : 'Article';
};

// Import video from Bunny Stream
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

        // Add imported video to list
        videos.value.unshift({
            ...response.data.video,
            usages: [],
        });

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

// Reset import form
const resetImportForm = () => {
    importBunnyVideoId.value = '';
    importDownloadThumbnail.value = true;
};

onMounted(() => {
    // Initial data is already loaded via props
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Gestion des vidéos" />

        <div class="px-5 py-6">
            <div class="mb-6 flex items-center justify-between">
                <Heading title="Gestion des vidéos" description="Gérez toutes les vidéos de votre site et voyez où elles sont utilisées" />

                <div class="flex items-center gap-3">
                    <Button type="button" variant="outline" @click="refreshVideos"> Actualiser </Button>
                    <Button type="button" variant="outline" @click="isImportModalOpen = true">
                        <Download class="mr-2 h-4 w-4" />
                        Importer depuis Bunny
                    </Button>
                    <Button type="button" @click="isUploadModalOpen = true">
                        <Plus class="mr-2 h-4 w-4" />
                        Ajouter une vidéo
                    </Button>
                </div>
            </div>

            <!-- Videos Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Toutes les vidéos ({{ videos.length }})</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-16">Aperçu</TableHead>
                                    <TableHead>Nom</TableHead>
                                    <TableHead class="w-24">Taille</TableHead>
                                    <TableHead class="w-32">Date</TableHead>
                                    <TableHead>Utilisations</TableHead>
                                    <TableHead class="w-32">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="video in videos" :key="video.id">
                                    <!-- Preview -->
                                    <TableCell>
                                        <div class="h-8 w-12 overflow-hidden rounded bg-gray-100">
                                            <img
                                                v-if="video.cover_picture?.path_small"
                                                :src="`/storage/${video.cover_picture.path_small}`"
                                                :alt="video.name"
                                                class="h-full w-full object-cover"
                                            />
                                            <div v-else class="flex h-full w-full items-center justify-center">
                                                <FileVideo class="h-4 w-4 text-gray-400" />
                                            </div>
                                        </div>
                                    </TableCell>

                                    <!-- Name -->
                                    <TableCell>
                                        <div>
                                            <div class="font-medium">{{ video.name }}</div>
                                            <div class="text-sm text-gray-500">ID: {{ video.id }}</div>
                                        </div>
                                    </TableCell>

                                    <!-- File Size -->
                                    <TableCell>
                                        <span class="text-sm">
                                            {{ formatFileSize(video.file_size || 0) }}
                                        </span>
                                    </TableCell>

                                    <!-- Date -->
                                    <TableCell>
                                        <span class="text-sm">
                                            {{ formatDate(video.created_at) }}
                                        </span>
                                    </TableCell>

                                    <!-- Usages -->
                                    <TableCell>
                                        <div v-if="video.usages.length > 0" class="space-y-1">
                                            <div v-for="usage in video.usages" :key="`${usage.type}-${usage.id}`" class="flex items-center gap-2">
                                                <span
                                                    class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium"
                                                    :class="{
                                                        'bg-blue-100 text-blue-800': usage.type === 'creation',
                                                        'bg-green-100 text-green-800': usage.type === 'blog_post',
                                                    }"
                                                >
                                                    {{ getUsageTypeLabel(usage) }}
                                                </span>
                                                <Link :href="usage.url" class="flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800">
                                                    {{ usage.title }}
                                                    <ExternalLink class="h-3 w-3" />
                                                </Link>
                                            </div>
                                        </div>
                                        <div v-else class="text-sm text-gray-500 italic">Non utilisée</div>
                                    </TableCell>

                                    <!-- Actions -->
                                    <TableCell>
                                        <div class="flex items-center gap-2">
                                            <Button type="button" variant="ghost" size="sm" @click="openEditModal(video)">
                                                <Edit class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                :disabled="video.usages.length > 0"
                                                @click="deleteVideo(video)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>

                        <div v-if="videos.length === 0" class="py-8 text-center text-gray-500">
                            <FileVideo class="mx-auto mb-4 h-12 w-12" />
                            <p>Aucune vidéo trouvée</p>
                            <p class="mt-1 text-sm">Commencez par ajouter votre première vidéo</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Upload Video Modal -->
        <Dialog v-model:open="isUploadModalOpen">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Ajouter une nouvelle vidéo</DialogTitle>
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

        <!-- Edit Video Modal -->
        <Dialog v-model:open="isEditModalOpen">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Modifier la vidéo</DialogTitle>
                </DialogHeader>

                <div class="space-y-4">
                    <!-- Video Name -->
                    <div>
                        <Label>Nom de la vidéo</Label>
                        <Input v-model="editVideoName" placeholder="Nom de la vidéo" />
                    </div>

                    <!-- Cover Picture -->
                    <div>
                        <Label>Image de couverture</Label>
                        <PictureInput :picture-id="editVideoCoverPictureId" @picture-selected="(id) => (editVideoCoverPictureId = id)" />
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" :disabled="loading" @click="isEditModalOpen = false"> Annuler </Button>
                    <Button type="button" :disabled="loading" @click="updateVideo">
                        <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                        <Edit v-else class="mr-2 h-4 w-4" />
                        Mettre à jour
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Import from Bunny Stream Modal -->
        <Dialog v-model:open="isImportModalOpen">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Importer une vidéo depuis Bunny Stream</DialogTitle>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-900 dark:bg-blue-900/20 dark:text-blue-100">
                        <p class="font-medium">Information</p>
                        <p class="mt-1">Cette fonctionnalité permet d'importer des vidéos déjà hébergées sur Bunny Stream sans avoir à les télécharger et re-uploader.</p>
                    </div>

                    <!-- Bunny Video ID -->
                    <div>
                        <Label>ID de la vidéo Bunny Stream</Label>
                        <Input
                            v-model="importBunnyVideoId"
                            placeholder="ex: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                            :disabled="loading"
                        />
                        <p class="mt-1 text-xs text-gray-500">
                            Vous pouvez trouver l'ID de la vidéo dans le tableau de bord Bunny Stream
                        </p>
                    </div>

                    <!-- Download Thumbnail Option -->
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
                    <Button type="button" variant="outline" :disabled="loading" @click="isImportModalOpen = false"> Annuler </Button>
                    <Button type="button" :disabled="!importBunnyVideoId.trim() || loading" @click="importFromBunny">
                        <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                        <Download v-else class="mr-2 h-4 w-4" />
                        Importer
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
