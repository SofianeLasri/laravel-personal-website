<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useRoute } from '@/composables/useRoute';
import type { Picture } from '@/types';
import axios from 'axios';
import { GripVertical, Image, Trash2, Upload } from 'lucide-vue-next';
import Sortable from 'sortablejs';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface GalleryImage {
    id: number;
    picture: Picture;
    caption: string;
    order: number;
}

interface Props {
    galleryId: number;
    initialImages?: GalleryImage[];
    locale: 'fr' | 'en';
}

const props = withDefaults(defineProps<Props>(), {
    initialImages: () => [],
});

const emit = defineEmits<{
    (e: 'update:images', images: GalleryImage[]): void;
}>();

const route = useRoute();

// State
const images = ref<GalleryImage[]>([...props.initialImages]);
const sortableInstance = ref<Sortable | null>(null);
const gridRef = ref<HTMLElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const uploading = ref(false);
const uploadProgress = ref<Record<string, number>>({});
const saving = ref(false);
const autoSaveTimeout = ref<ReturnType<typeof setTimeout> | null>(null);
const autoSaveStatus = ref<'idle' | 'saving' | 'saved' | 'error'>('idle');

// Computed
const isEmpty = computed(() => images.value.length === 0);
const hasUnsavedChanges = ref(false);

// Watch for changes and emit
watch(
    images,
    (newImages) => {
        emit('update:images', newImages);
        hasUnsavedChanges.value = true;
    },
    { deep: true },
);

// Watch for changes in initial images (when data is loaded from database)
// Only update if we don't have local changes OR if we're loading for the first time
watch(
    () => props.initialImages,
    (newInitialImages, oldInitialImages) => {
        // Only update from props if:
        // 1. This is the initial load (oldInitialImages is undefined or we have no images)
        // 2. OR the gallery was just created (images.value is empty and we're getting data from server)
        const isInitialLoad = oldInitialImages === undefined || (images.value.length === 0 && oldInitialImages.length === 0);
        const shouldUpdateFromProps = isInitialLoad && !hasUnsavedChanges.value;

        if (shouldUpdateFromProps) {
            images.value = [...(newInitialImages || [])];
        }
    },
    { deep: true, immediate: true },
);

// Initialize sortable
onMounted(() => {
    if (gridRef.value) {
        sortableInstance.value = Sortable.create(gridRef.value, {
            animation: 150,
            handle: '.drag-handle',
            onEnd: (evt) => {
                if (evt.oldIndex !== undefined && evt.newIndex !== undefined) {
                    const item = images.value.splice(evt.oldIndex, 1)[0];
                    images.value.splice(evt.newIndex, 0, item);
                    updateOrders();

                    // Auto-save after reordering
                    autoSaveGallery();
                }
            },
        });
    }
});

onUnmounted(() => {
    if (sortableInstance.value) {
        sortableInstance.value.destroy();
    }
    if (autoSaveTimeout.value) {
        clearTimeout(autoSaveTimeout.value);
    }
});

// Methods
const updateOrders = () => {
    images.value.forEach((image, index) => {
        image.order = index + 1;
    });
};

const openFileDialog = () => {
    fileInput.value?.click();
};

const handleFileUpload = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    const files = target.files;

    if (!files || files.length === 0) return;

    uploading.value = true;

    try {
        for (const file of Array.from(files)) {
            await uploadSingleFile(file);
        }
    } finally {
        uploading.value = false;
        // Reset file input
        if (target) target.value = '';
    }
};

const uploadSingleFile = async (file: File): Promise<void> => {
    const fileId = `${file.name}-${Date.now()}`;
    uploadProgress.value[fileId] = 0;

    try {
        const formData = new FormData();
        formData.append('picture', file);

        const response = await axios.post(route('dashboard.api.pictures.store'), formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
            onUploadProgress: (progressEvent) => {
                if (progressEvent.total) {
                    const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                    uploadProgress.value[fileId] = percentCompleted;
                }
            },
        });

        const picture: Picture = response.data;

        // Add to images array
        images.value.push({
            id: picture.id,
            picture: {
                id: picture.id,
                filename: picture.filename,
                path_original: picture.path_original,
                path_medium: picture.path_medium,
                path_small: picture.path_small,
                path_thumbnail: picture.path_thumbnail,
                path_large: picture.path_large,
            },
            caption: '',
            order: images.value.length + 1,
        });

        toast.success(`Image "${file.name}" ajoutée avec succès`);

        // Auto-save after successful upload
        autoSaveGallery();
    } catch (error) {
        console.error("Erreur lors de l'upload:", error);
        toast.error(`Erreur lors de l'upload de "${file.name}"`);
    } finally {
        delete uploadProgress.value[fileId];
    }
};

const removeImage = (index: number) => {
    images.value.splice(index, 1);
    updateOrders();
    toast.success('Image supprimée');

    // Auto-save after removing image
    autoSaveGallery();
};

const updateCaption = (index: number, caption: string) => {
    images.value[index].caption = caption;
};

const saveChanges = async () => {
    if (images.value.length === 0) {
        // Allow saving empty gallery to clear it
        // toast.error('Ajoutez au moins une image avant de sauvegarder');
        // return;
    }

    saving.value = true;

    try {
        const payload = {
            pictures: images.value.map((img) => ({
                id: img.picture.id,
                caption: img.caption,
                order: img.order,
            })),
            locale: props.locale,
        };

        await axios.put(
            route('dashboard.api.blog-content-galleries.update-pictures', {
                blog_content_gallery: props.galleryId,
            }),
            payload,
        );

        hasUnsavedChanges.value = false;
        toast.success('Galerie sauvegardée avec succès');
        return true;
    } catch (error) {
        console.error('Erreur lors de la sauvegarde:', error);
        toast.error('Erreur lors de la sauvegarde');
        return false;
    } finally {
        saving.value = false;
    }
};

// Auto-save function with debouncing
const autoSaveGallery = () => {
    // Clear any existing timeout
    if (autoSaveTimeout.value) {
        clearTimeout(autoSaveTimeout.value);
    }

    // Set status to indicate pending save
    autoSaveStatus.value = 'saving';

    // Debounce the save operation
    autoSaveTimeout.value = setTimeout(async () => {
        try {
            const payload = {
                pictures: images.value.map((img) => ({
                    id: img.picture.id,
                    caption: img.caption,
                    order: img.order,
                })),
                locale: props.locale,
            };

            await axios.put(
                route('dashboard.api.blog-content-galleries.update-pictures', {
                    blog_content_gallery: props.galleryId,
                }),
                payload,
            );

            autoSaveStatus.value = 'saved';
            hasUnsavedChanges.value = false;

            // Reset status after 2 seconds
            setTimeout(() => {
                if (autoSaveStatus.value === 'saved') {
                    autoSaveStatus.value = 'idle';
                }
            }, 2000);
        } catch (error) {
            console.error('Erreur lors de la sauvegarde automatique:', error);
            autoSaveStatus.value = 'error';
            hasUnsavedChanges.value = true;
        }
    }, 1000); // Wait 1 second before auto-saving
};

// Handle drag & drop on the container
const handleDrop = (event: DragEvent) => {
    event.preventDefault();
    event.stopPropagation();

    const files = event.dataTransfer?.files;
    if (files && files.length > 0) {
        void handleFileUpload({ target: { files } } as Event & { target: { files: FileList } });
    }
};

const handleDragOver = (event: DragEvent) => {
    event.preventDefault();
};

// Expose the saveChanges method so parent can call it
defineExpose({
    saveChanges,
});
</script>

<template>
    <div class="space-y-4">
        <!-- Upload Zone -->
        <div
            class="rounded-lg border-2 border-dashed p-6 text-center transition-colors"
            :class="[
                uploading
                    ? 'border-blue-300 bg-blue-50 dark:border-blue-700 dark:bg-blue-950/30'
                    : 'border-gray-300 hover:border-gray-400 dark:border-gray-600 dark:hover:border-gray-500',
            ]"
            @drop="handleDrop"
            @dragover="handleDragOver"
        >
            <input ref="fileInput" type="file" multiple accept="image/*" class="hidden" @change="handleFileUpload" />

            <div class="flex flex-col items-center gap-2">
                <Upload class="h-8 w-8 text-gray-400" />
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Glissez-déposez vos images ici ou
                        <button type="button" class="text-blue-600 underline hover:text-blue-700" @click="openFileDialog">
                            cliquez pour sélectionner
                        </button>
                    </p>
                    <p class="mt-1 text-xs text-gray-500">Formats supportés: JPG, PNG, GIF, WebP</p>
                </div>
            </div>

            <!-- Upload Progress -->
            <div v-if="Object.keys(uploadProgress).length > 0" class="mt-4 space-y-2">
                <div v-for="(progress, fileId) in uploadProgress" :key="fileId" class="h-2 w-full rounded-full bg-gray-200">
                    <div class="h-2 rounded-full bg-blue-600 transition-all duration-300" :style="{ width: `${progress}%` }"></div>
                </div>
            </div>
        </div>

        <!-- Images Grid -->
        <div v-if="!isEmpty" class="space-y-4">
            <div class="flex items-center">
                <Label>Images ({{ images.length }})</Label>
            </div>

            <div ref="gridRef" class="responsive-gallery-grid gap-2">
                <div
                    v-for="(image, index) in images"
                    :key="image.picture.id"
                    class="group relative rounded-lg border bg-white p-2 shadow-sm transition-all hover:shadow-md dark:bg-gray-800"
                >
                    <!-- Drag Handle -->
                    <div
                        class="drag-handle absolute top-2 left-2 cursor-move rounded bg-white p-1 opacity-0 shadow-sm transition-opacity group-hover:opacity-100 dark:bg-gray-700"
                    >
                        <GripVertical class="h-4 w-4 text-gray-500" />
                    </div>

                    <!-- Remove Button -->
                    <button
                        type="button"
                        class="absolute top-2 right-2 rounded bg-red-500 p-1 text-white opacity-0 shadow-sm transition-opacity group-hover:opacity-100 hover:bg-red-600"
                        @click="removeImage(index)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </button>

                    <!-- Image -->
                    <div class="mb-2 aspect-square overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-700">
                        <img
                            :src="`/storage/${image.picture.path_medium || image.picture.path_original}`"
                            :alt="image.caption || 'Image de galerie'"
                            class="h-full w-full object-cover"
                            @error="
                                (e) => {
                                    // Fallback to original if medium fails to load
                                    if (image.picture.path_medium && e.target.src.includes(image.picture.path_medium)) {
                                        e.target.src = `/storage/${image.picture.path_original}`;
                                    }
                                }
                            "
                        />
                    </div>

                    <!-- Caption Input -->
                    <div class="space-y-1">
                        <Label class="text-sm">Description ({{ locale.toUpperCase() }})</Label>
                        <Textarea
                            :value="image.caption"
                            placeholder="Ajoutez une description pour cette image..."
                            class="min-h-[40px] resize-none text-sm"
                            @input="(e: Event) => updateCaption(index, (e.target as HTMLTextAreaElement).value)"
                        />
                    </div>

                    <!-- Order Badge -->
                    <div class="absolute bottom-2 left-2">
                        <span
                            class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                        >
                            {{ image.order }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="py-8 text-center text-gray-500 dark:text-gray-400">
            <Image class="mx-auto mb-4 h-12 w-12" />
            <p>Aucune image dans cette galerie</p>
            <p class="mt-1 text-sm">Ajoutez des images en utilisant la zone de dépôt ci-dessus</p>
        </div>

        <!-- Auto-save status indicator -->
        <div
            v-if="autoSaveStatus !== 'idle' || hasUnsavedChanges"
            class="flex items-center gap-2 rounded-md p-3 text-sm"
            :class="{
                'bg-blue-50 text-blue-800 dark:bg-blue-900/20 dark:text-blue-200': autoSaveStatus === 'saving',
                'bg-green-50 text-green-800 dark:bg-green-900/20 dark:text-green-200': autoSaveStatus === 'saved',
                'bg-red-50 text-red-800 dark:bg-red-900/20 dark:text-red-200': autoSaveStatus === 'error',
                'bg-yellow-50 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-200': hasUnsavedChanges && autoSaveStatus === 'idle',
            }"
        >
            <div v-if="autoSaveStatus === 'saving'" class="h-2 w-2 animate-spin rounded-full border-b-2 border-blue-600"></div>
            <div v-else-if="autoSaveStatus === 'saved'" class="text-green-600">✓</div>
            <div v-else-if="autoSaveStatus === 'error'" class="text-red-600">⚠</div>
            <div v-else-if="hasUnsavedChanges" class="h-2 w-2 animate-pulse rounded-full bg-yellow-500"></div>
            <span>
                {{
                    autoSaveStatus === 'saving'
                        ? 'Sauvegarde automatique en cours...'
                        : autoSaveStatus === 'saved'
                          ? 'Galerie sauvegardée automatiquement'
                          : autoSaveStatus === 'error'
                            ? 'Erreur lors de la sauvegarde automatique'
                            : 'Modifications en attente de sauvegarde'
                }}
            </span>
        </div>
    </div>
</template>

<style scoped>
.responsive-gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));

    /* Responsive breakpoints */
    @media (min-width: 640px) {
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    }

    @media (min-width: 768px) {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    }

    @media (min-width: 1024px) {
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    }

    @media (min-width: 1280px) {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    }
}
</style>
