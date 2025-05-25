<script setup lang="ts">
import Heading from '@/components/dashboard/Heading.vue';
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationFirst,
    PaginationItem,
    PaginationLast,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowUpDown, Download, Eye, Image as ImageIcon, Loader2, MoreHorizontal, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';

// Types
interface OptimizedPicture {
    id: number;
    variant: string;
    path: string;
    format: string;
}

interface Picture {
    id: number;
    filename: string;
    width: number | null;
    height: number | null;
    size: number | null;
    path_original: string | null;
    created_at: string;
    optimized_pictures_count: number;
    optimized_pictures: OptimizedPicture[];
}

interface PaginationData {
    data: Picture[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

interface Filters {
    search: string;
    sort_by: string;
    sort_direction: string;
}

// Props
const props = defineProps<{
    pictures: PaginationData;
    filters: Filters;
}>();

// État local
const isLoading = ref(false);
const isDeleteDialogOpen = ref(false);
const isDetailDialogOpen = ref(false);
const currentPicture = ref<Picture | null>(null);
const searchTerm = ref(props.filters.search);
const sortBy = ref(props.filters.sort_by);
const sortDirection = ref(props.filters.sort_direction);

// Breadcrumbs
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Images',
        href: route('dashboard.pictures.index', undefined, false),
    },
];

// Computed
const formatBytes = (bytes: number | null): string => {
    if (!bytes) return 'N/A';

    if (bytes === 0) return '0 Bytes';

    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('fr-FR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getThumbnailUrl = (picture: Picture): string => {
    const thumbnail = picture.optimized_pictures?.find((op) => op.variant === 'medium' && op.format === 'webp');

    if (thumbnail) {
        return `/storage/${thumbnail.path}`;
    }

    // Fallback to original if no thumbnail
    if (picture.path_original) {
        return `/storage/${picture.path_original}`;
    }

    return '';
};

// Watchers
watch(
    [searchTerm, sortBy, sortDirection],
    () => {
        applyFilters();
    },
    { debounce: 500 },
);

// Méthodes
const applyFilters = () => {
    const params = new URLSearchParams();

    if (searchTerm.value) {
        params.append('search', searchTerm.value);
    }

    if (sortBy.value) {
        params.append('sort_by', sortBy.value);
    }

    if (sortDirection.value) {
        params.append('sort_direction', sortDirection.value);
    }

    router.get(route('dashboard.pictures.index'), Object.fromEntries(params), {
        preserveState: true,
        preserveScroll: true,
    });
};

const confirmDelete = (picture: Picture) => {
    currentPicture.value = picture;
    isDeleteDialogOpen.value = true;
};

const showDetails = (picture: Picture) => {
    currentPicture.value = picture;
    isDetailDialogOpen.value = true;
};

const deletePicture = async () => {
    if (!currentPicture.value) return;

    isLoading.value = true;

    try {
        await axios.delete(route('dashboard.api.pictures.destroy', { picture: currentPicture.value.id }));

        toast.success('Image supprimée avec succès');
        isDeleteDialogOpen.value = false;

        // Refresh the page data
        router.reload({ only: ['pictures'] });
    } catch (error) {
        console.error('Erreur lors de la suppression:', error);
        toast.error("Erreur lors de la suppression de l'image");
    } finally {
        isLoading.value = false;
    }
};

const downloadOriginal = (picture: Picture) => {
    if (!picture.path_original) return;

    const link = document.createElement('a');
    link.href = `/storage/${picture.path_original}`;
    link.download = picture.filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

const handlePageChange = (page: number) => {
    if (page < 1 || page > props.pictures.last_page) return;

    const params = new URLSearchParams(window.location.search);
    params.set('page', page.toString());

    router.get(route('dashboard.pictures.index'), Object.fromEntries(params), {
        preserveState: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Images" />

        <div class="px-5 py-6">
            <div class="flex items-center justify-between">
                <Heading
                    title="Images"
                    :description="`Gérez toutes les images uploadées sur le site. ${pictures.total} image${pictures.total > 1 ? 's' : ''} au total.`"
                />
            </div>

            <!-- Filtres -->
            <div class="mt-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="flex gap-2">
                    <div class="relative">
                        <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                        <Input v-model="searchTerm" placeholder="Rechercher par nom de fichier..." class="pl-9 md:w-64" />
                    </div>
                </div>

                <div class="flex gap-2">
                    <Select v-model="sortBy">
                        <SelectTrigger class="w-40">
                            <SelectValue placeholder="Trier par" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="created_at">Date d'upload</SelectItem>
                            <SelectItem value="filename">Nom de fichier</SelectItem>
                            <SelectItem value="size">Taille</SelectItem>
                            <SelectItem value="width">Largeur</SelectItem>
                            <SelectItem value="height">Hauteur</SelectItem>
                        </SelectContent>
                    </Select>

                    <Button variant="outline" size="icon" @click="sortDirection = sortDirection === 'asc' ? 'desc' : 'asc'">
                        <ArrowUpDown class="h-4 w-4" />
                    </Button>
                </div>
            </div>

            <!-- Grille d'images -->
            <div class="mt-6">
                <div v-if="pictures.data.length > 0" class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <Card v-for="picture in pictures.data" :key="picture.id" class="group overflow-hidden">
                        <CardContent class="p-0">
                            <!-- Image -->
                            <div class="bg-muted relative aspect-square overflow-hidden">
                                <img
                                    v-if="getThumbnailUrl(picture)"
                                    :src="getThumbnailUrl(picture)"
                                    :alt="picture.filename"
                                    class="h-full w-full object-cover transition-transform group-hover:scale-105"
                                    loading="lazy"
                                />
                                <div v-else class="flex h-full items-center justify-center">
                                    <ImageIcon class="text-muted-foreground h-12 w-12" />
                                </div>

                                <!-- Overlay avec actions -->
                                <div class="absolute inset-0 bg-black/0 transition-colors group-hover:bg-black/20">
                                    <div class="absolute top-2 right-2 opacity-0 transition-opacity group-hover:opacity-100">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button variant="secondary" size="icon" class="h-8 w-8">
                                                    <MoreHorizontal class="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem @click="showDetails(picture)">
                                                    <Eye class="mr-2 h-4 w-4" />
                                                    Voir les détails
                                                </DropdownMenuItem>
                                                <DropdownMenuItem v-if="picture.path_original" @click="downloadOriginal(picture)">
                                                    <Download class="mr-2 h-4 w-4" />
                                                    Télécharger
                                                </DropdownMenuItem>
                                                <DropdownMenuItem @click="confirmDelete(picture)" class="text-destructive focus:text-destructive">
                                                    <Trash2 class="mr-2 h-4 w-4" />
                                                    Supprimer
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </div>
                            </div>

                            <!-- Métadonnées -->
                            <div class="p-3">
                                <h3 class="truncate text-sm font-medium" :title="picture.filename">
                                    {{ picture.filename }}
                                </h3>

                                <div class="mt-2 flex flex-wrap gap-1">
                                    <Badge variant="secondary" class="text-xs">
                                        {{ formatBytes(picture.size) }}
                                    </Badge>

                                    <Badge v-if="picture.width && picture.height" variant="outline" class="text-xs">
                                        {{ picture.width }}×{{ picture.height }}
                                    </Badge>

                                    <Badge variant="outline" class="text-xs"> {{ picture.optimized_pictures_count }} variantes </Badge>
                                </div>

                                <p class="text-muted-foreground mt-1 text-xs">
                                    {{ formatDate(picture.created_at) }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- État vide -->
                <div v-else class="border-border text-muted-foreground rounded-lg border p-8 text-center">
                    <ImageIcon class="mx-auto mb-4 h-12 w-12" />
                    <p class="mb-4">Aucune image trouvée.</p>
                    <p class="text-sm">
                        {{
                            searchTerm
                                ? 'Essayez de modifier vos critères de recherche.'
                                : "Les images seront automatiquement ajoutées lors de l'upload de contenu."
                        }}
                    </p>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="pictures.last_page > 1" class="mt-6 flex justify-center">
                <Pagination
                    :total="pictures.total"
                    :items-per-page="pictures.per_page"
                    :default-page="pictures.current_page"
                    show-edges
                    :sibling-count="1"
                    @update:page="handlePageChange"
                    v-slot="{ page }"
                >
                    <PaginationContent v-slot="{ items }" class="flex items-center gap-1">
                        <PaginationFirst />
                        <PaginationPrevious />

                        <template v-for="(item, index) in items">
                            <PaginationItem v-if="item.type === 'page'" :key="index" :value="item.value" as-child>
                                <Button class="h-10 w-10 p-0" :variant="item.value === page ? 'default' : 'outline'">
                                    {{ item.value }}
                                </Button>
                            </PaginationItem>
                            <PaginationEllipsis v-else :key="item.type" :index="index" />
                        </template>

                        <PaginationNext />
                        <PaginationLast />
                    </PaginationContent>
                </Pagination>
            </div>

            <!-- Information sur le nombre d'éléments -->
            <div v-if="pictures.total > 0" class="text-muted-foreground mt-2 text-center text-sm">
                Affichage de {{ pictures.from || 1 }} à {{ pictures.to || pictures.total }} sur {{ pictures.total }} image{{
                    pictures.total > 1 ? 's' : ''
                }}
            </div>
        </div>

        <!-- Dialog de détails -->
        <Dialog v-model:open="isDetailDialogOpen">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Détails de l'image</DialogTitle>
                </DialogHeader>

                <div v-if="currentPicture" class="space-y-4">
                    <!-- Image preview -->
                    <div class="flex justify-center">
                        <img
                            v-if="getThumbnailUrl(currentPicture)"
                            :src="getThumbnailUrl(currentPicture)"
                            :alt="currentPicture.filename"
                            class="max-h-64 rounded-lg object-contain"
                        />
                    </div>

                    <!-- Informations -->
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="font-medium">Nom de fichier</p>
                            <p class="text-muted-foreground">{{ currentPicture.filename }}</p>
                        </div>

                        <div>
                            <p class="font-medium">Taille</p>
                            <p class="text-muted-foreground">{{ formatBytes(currentPicture.size) }}</p>
                        </div>

                        <div v-if="currentPicture.width && currentPicture.height">
                            <p class="font-medium">Dimensions</p>
                            <p class="text-muted-foreground">{{ currentPicture.width }}×{{ currentPicture.height }}px</p>
                        </div>

                        <div>
                            <p class="font-medium">Date d'upload</p>
                            <p class="text-muted-foreground">{{ formatDate(currentPicture.created_at) }}</p>
                        </div>

                        <div>
                            <p class="font-medium">Variantes optimisées</p>
                            <p class="text-muted-foreground">{{ currentPicture.optimized_pictures_count }} variantes</p>
                        </div>
                    </div>

                    <!-- Variantes disponibles -->
                    <div v-if="currentPicture.optimized_pictures && currentPicture.optimized_pictures.length > 0">
                        <p class="font-medium">Formats disponibles</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <Badge
                                v-for="optimized in currentPicture.optimized_pictures"
                                :key="`${optimized.variant}-${optimized.format}`"
                                variant="outline"
                                class="text-xs"
                            >
                                {{ optimized.variant }} ({{ optimized.format }})
                            </Badge>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Dialog de confirmation de suppression -->
        <AlertDialog v-model:open="isDeleteDialogOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Confirmer la suppression</AlertDialogTitle>
                    <AlertDialogDescription>
                        Êtes-vous sûr de vouloir supprimer cette image ? Cette action supprimera également toutes les variantes optimisées. Cette
                        action est irréversible.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="isDeleteDialogOpen = false" :disabled="isLoading"> Annuler </AlertDialogCancel>
                    <AlertDialogAction
                        @click="deletePicture"
                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        :disabled="isLoading"
                    >
                        <Loader2 v-if="isLoading" class="mr-2 h-4 w-4 animate-spin" />
                        Supprimer
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>