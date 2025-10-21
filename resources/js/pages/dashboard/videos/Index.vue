<script setup lang="ts">
import Heading from '@/components/dashboard/Heading.vue';
import VideoManager from '@/components/dashboard/VideoManager.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useRoute } from '@/composables/useRoute';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Video } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { Download, Edit, ExternalLink, FileVideo, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
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
const isUploadModalOpen = ref(false);
const isEditModalOpen = ref(false);
const isImportModalOpen = ref(false);
const editingVideo = ref<VideoWithUsage | null>(null);

// Ref to VideoManager for helpers
// eslint-disable-next-line @typescript-eslint/no-redundant-type-constituents
const videoManager = ref<InstanceType<typeof VideoManager> | null>(null);

// VideoManager event handlers
const handleVideoUploaded = (video: Video) => {
    // Add new video to list with empty usages
    videos.value.unshift({
        ...video,
        usages: [],
    });
};

const handleVideoUpdated = (video: Video) => {
    // Update video in list
    const index = videos.value.findIndex((v) => v.id === video.id);
    if (index !== -1) {
        videos.value[index] = {
            ...video,
            usages: videos.value[index].usages, // Preserve usages
        };
    }
    editingVideo.value = null;
};

const handleVideoImported = (video: Video) => {
    // Add imported video to list with empty usages
    videos.value.unshift({
        ...video,
        usages: [],
    });
};

// Edit video
const openEditModal = (video: VideoWithUsage) => {
    editingVideo.value = video;
    isEditModalOpen.value = true;
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
                                                v-if="video.cover_picture?.path_original"
                                                :src="`/storage/${video.cover_picture.path_original}`"
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

        <!-- VideoManager with all modals -->
        <VideoManager
            ref="videoManager"
            v-model:show-upload-modal="isUploadModalOpen"
            v-model:show-edit-modal="isEditModalOpen"
            v-model:show-import-modal="isImportModalOpen"
            :editing-video="editingVideo"
            :allow-visibility-edit="true"
            :allow-thumbnail-download="true"
            @video-uploaded="handleVideoUploaded"
            @video-updated="handleVideoUpdated"
            @video-imported="handleVideoImported"
        />
    </AppLayout>
</template>
