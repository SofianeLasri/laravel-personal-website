<script setup lang="ts">
import ContentGalleryManager from '@/components/dashboard/ContentGalleryManager.vue';
import ContentVideoManager from '@/components/dashboard/ContentVideoManager.vue';
import MarkdownEditor from '@/components/dashboard/MarkdownEditor.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { useRoute } from '@/composables/useRoute';
import type { Content, Picture, Video } from '@/types';
import axios from 'axios';
import { GripVertical, Image, Text, Trash2, Video as VideoIcon } from 'lucide-vue-next';
import Sortable from 'sortablejs';
import { onMounted, onUnmounted, ref, watchEffect } from 'vue';
import { toast } from 'vue-sonner';

interface Translation {
    locale: string;
    text: string;
}

interface TranslationKey {
    id: number;
    key: string;
    translations: Translation[];
}

interface PicturePivot {
    order: number;
    caption_translation_key_id?: number;
    caption_translation_key?: TranslationKey;
}

interface PictureWithPivot {
    id: number;
    path_original: string;
    path_medium: string;
    path_small: string;
    path_thumbnail?: string;
    path_large?: string;
    pivot?: PicturePivot;
}

interface GalleryImage {
    id: number;
    picture: {
        id: number;
        path_original: string;
        path_medium: string;
        path_small: string;
        path_thumbnail?: string;
        path_large?: string;
    };
    caption: string;
    order: number;
}
interface Props {
    draftId: number; // Now required since we only show this component when draft exists
    contents: Content[];
    pictures: Picture[];
    videos: Video[];
    locale: 'fr' | 'en';
}

const props = defineProps<Props>();
const route = useRoute();

const localContents = ref<Content[]>([...props.contents]);
const sortableInstance = ref<Sortable | null>(null);
const contentListRef = ref<HTMLElement | null>(null);

// Refs for gallery managers
const galleryRefs = ref<Record<number, InstanceType<typeof ContentGalleryManager>>>({});

// Cache local pour les contenus en cours d'édition
const contentCache = ref<Record<number, string>>({});
const savingStatus = ref<Record<number, 'idle' | 'saving' | 'saved' | 'error'>>({});
const saveTimeouts = ref<Record<number, ReturnType<typeof setTimeout>>>({});

// Initialiser le cache avec les contenus existants
const initializeContentCache = () => {
    localContents.value.forEach((content) => {
        if (getContentTypeFromClass(content.content_type) === 'markdown') {
            contentCache.value[content.content_id] =
                content.content?.translation_key?.translations?.find((t) => t.locale === props.locale)?.text ?? '';
            savingStatus.value[content.content_id] = 'idle';
        }
    });
};

// Initialize sortable and cache on mount
onMounted(() => {
    if (contentListRef.value) {
        sortableInstance.value = Sortable.create(contentListRef.value, {
            animation: 150,
            handle: '.drag-handle',
            onEnd: (evt) => {
                if (evt.oldIndex !== undefined && evt.newIndex !== undefined) {
                    const item = localContents.value.splice(evt.oldIndex, 1)[0];
                    localContents.value.splice(evt.newIndex, 0, item);
                    void updateContentOrder();
                }
            },
        });
    }

    // Initialiser le cache des contenus
    initializeContentCache();
});

// Protection contre la perte de données
const hasUnsavedChanges = ref(false);

// Surveiller les modifications non sauvegardées
watchEffect(() => {
    const hasSavingContent = Object.values(savingStatus.value).some((status) => status === 'saving');
    const hasErrorContent = Object.values(savingStatus.value).some((status) => status === 'error');

    // Vérifier s'il y a des modifications en attente (contenu qui a changé mais pas encore sauvegardé)
    const hasPendingChanges = Object.entries(saveTimeouts.value).some(([_contentId, timeout]) => {
        return timeout !== null;
    });

    hasUnsavedChanges.value = hasSavingContent || hasErrorContent || hasPendingChanges;
});

// Ajouter un gestionnaire d'événement pour avertir avant de quitter
const beforeUnloadHandler = (event: BeforeUnloadEvent): void => {
    if (hasUnsavedChanges.value) {
        event.preventDefault();
        event.returnValue = '';
    }
};

onMounted(() => {
    window.addEventListener('beforeunload', beforeUnloadHandler);
});

// Nettoyer les timeouts et les event listeners lors de la destruction du composant
onUnmounted(() => {
    Object.values(saveTimeouts.value).forEach((timeout) => {
        if (timeout) clearTimeout(timeout);
    });
    window.removeEventListener('beforeunload', beforeUnloadHandler);
});

const contentTypes = [
    { value: 'markdown', label: 'Texte (Markdown)', icon: Text },
    { value: 'gallery', label: 'Image(s) / Galerie', icon: Image },
    { value: 'video', label: 'Vidéo', icon: VideoIcon },
];

const addContent = async (type: string) => {
    try {
        // First, ensure all galleries are saved
        const galleriesSaved = await saveAllGalleries();
        if (!galleriesSaved) {
            toast.error("Veuillez sauvegarder les galeries avant d'ajouter un nouveau bloc");
            return;
        }

        let contentId: number;
        let newContent: Content;

        // Create the content based on type
        if (type === 'markdown') {
            // Create a new markdown content
            const response = await axios.post(route('dashboard.api.blog-content-markdown.store'), {
                content: '',
                locale: props.locale,
            });
            contentId = response.data.id;
            newContent = response.data;
        } else if (type === 'gallery') {
            // Create a new gallery content
            const response = await axios.post(route('dashboard.api.blog-content-gallery.store'), {
                layout: 'grid',
                columns: 2,
                picture_ids: [],
                captions: [],
                locale: props.locale,
            });
            contentId = response.data.id;
            newContent = response.data;
        } else if (type === 'video') {
            // Create a new video content without a video initially
            const response = await axios.post(route('dashboard.api.blog-content-video.store'), {
                video_id: null, // No video selected initially
                caption: '',
                locale: props.locale,
            });
            contentId = response.data.id;
            newContent = response.data;
        } else {
            return;
        }

        // Add to blog post draft contents
        const response = await axios.post(route('dashboard.api.blog-post-draft-contents.store'), {
            blog_post_draft_id: props.draftId,
            content_type:
                type === 'markdown'
                    ? 'App\\Models\\ContentMarkdown'
                    : type === 'gallery'
                      ? 'App\\Models\\ContentGallery'
                      : 'App\\Models\\ContentVideo',
            content_id: contentId,
            order: localContents.value.length + 1,
        });

        // Create a properly structured content object for the new block
        const newBlock = {
            ...response.data,
            content: newContent,
        };

        localContents.value.push(newBlock);

        // Initialiser le cache et le statut pour le nouveau contenu markdown
        if (type === 'markdown') {
            contentCache.value[contentId] = '';
            savingStatus.value[contentId] = 'idle';
        }

        toast.success('Bloc de contenu ajouté');
    } catch (error: unknown) {
        console.error("Erreur lors de l'ajout du contenu:", error);

        if (axios.isAxiosError(error)) {
            console.error("Détails de l'erreur:", error.response?.data);

            if (error.response?.status === 422 && error.response?.data?.errors) {
                const errors = Object.values(error.response.data.errors).flat();
                toast.error(`Erreurs de validation: ${errors.join(', ')}`);
            } else {
                toast.error("Erreur lors de l'ajout du contenu");
            }
        } else {
            toast.error("Erreur lors de l'ajout du contenu");
        }
    }
};

const removeContent = async (index: number) => {
    const content = localContents.value[index];

    if (!content.id) {
        localContents.value.splice(index, 1);
        return;
    }

    try {
        await axios.delete(
            route('dashboard.api.blog-post-draft-contents.destroy', {
                blog_post_draft_content: content.id,
            }),
        );

        localContents.value.splice(index, 1);
        toast.success('Bloc de contenu supprimé');

        // Update order for remaining items
        await updateContentOrder();
    } catch (error: unknown) {
        console.error('Erreur lors de la suppression:', error);
        toast.error('Erreur lors de la suppression');
    }
};

const updateContentOrder = async () => {
    // Update local order
    localContents.value.forEach((content, index) => {
        content.order = index;
    });

    try {
        await axios.post(route('dashboard.api.blog-post-draft-contents.reorder', { blog_post_draft: props.draftId }), {
            content_ids: localContents.value.map((c) => c.id),
        });
    } catch (error: unknown) {
        console.error('Erreur lors de la réorganisation:', error);
        toast.error('Erreur lors de la réorganisation');
    }
};

// Fonction debounced pour sauvegarder le contenu
const debouncedSave = (contentId: number, text: string) => {
    // Annuler la sauvegarde précédente si elle existe
    if (saveTimeouts.value[contentId]) {
        clearTimeout(saveTimeouts.value[contentId]);
    }

    // Programmer une nouvelle sauvegarde après 1.5 secondes
    saveTimeouts.value[contentId] = setTimeout(() => {
        void saveMarkdownContent(contentId, text);
    }, 1500);
};

const saveMarkdownContent = async (contentId: number, text: string) => {
    savingStatus.value[contentId] = 'saving';

    try {
        await axios.put(
            route('dashboard.api.blog-content-markdown.update', {
                blog_content_markdown: contentId,
            }),
            {
                content: text,
                locale: props.locale,
            },
        );

        savingStatus.value[contentId] = 'saved';

        // Nettoyer le timeout après sauvegarde réussie
        if (saveTimeouts.value[contentId]) {
            clearTimeout(saveTimeouts.value[contentId]);
            delete saveTimeouts.value[contentId];
        }

        // Marquer comme "idle" après 2 secondes
        setTimeout(() => {
            if (savingStatus.value[contentId] === 'saved') {
                savingStatus.value[contentId] = 'idle';
            }
        }, 2000);
    } catch (error: unknown) {
        console.error('Erreur lors de la mise à jour:', error);
        savingStatus.value[contentId] = 'error';

        // Nettoyer le timeout même en cas d'erreur
        if (saveTimeouts.value[contentId]) {
            clearTimeout(saveTimeouts.value[contentId]);
            delete saveTimeouts.value[contentId];
        }
    }
};

const updateMarkdownContent = (contentId: number, text: string) => {
    // Mise à jour immédiate du cache local
    contentCache.value[contentId] = text;

    // Sauvegarde debounced
    debouncedSave(contentId, text);
};

const updateGalleryComplete = (contentId: number, images: GalleryImage[]) => {
    // This method is handled by the ContentGalleryManager component itself
    // We just need to refresh the content to show updated data
    // Gallery updates are managed automatically by the child component
    void contentId;
    void images;
};

const getContentTypeLabel = (type: string) => {
    const contentType = contentTypes.find((t) => t.value === type);
    return contentType?.label ?? type;
};

const getContentTypeFromClass = (className: string): string => {
    if (className.includes('ContentMarkdown')) return 'markdown';
    if (className.includes('ContentGallery')) return 'gallery';
    if (className.includes('ContentVideo')) return 'video';
    return 'unknown';
};

// Helper function to get translated caption from translation key
const getTranslatedCaption = (captionTranslationKey: TranslationKey | undefined, locale: string): string => {
    if (!captionTranslationKey?.translations) return '';

    const translation = captionTranslationKey.translations.find((t: Translation) => t.locale === locale);
    return translation?.text ?? '';
};

// Transform gallery data for the ContentGalleryManager
const transformGalleryImages = (content: { pictures?: PictureWithPivot[] }): GalleryImage[] => {
    if (!content?.pictures) return [];

    return content.pictures.map((picture: PictureWithPivot) => ({
        id: picture.id,
        picture: {
            id: picture.id,
            path_original: picture.path_original,
            path_medium: picture.path_medium,
            path_small: picture.path_small,
            path_thumbnail: picture.path_thumbnail,
            path_large: picture.path_large,
        },
        caption: getTranslatedCaption(picture.pivot?.caption_translation_key, props.locale) ?? '',
        order: picture.pivot?.order ?? 1,
    }));
};

// Save all galleries method
const saveAllGalleries = async (): Promise<boolean> => {
    const galleryContents = localContents.value.filter((content) => getContentTypeFromClass(content.content_type) === 'gallery');

    // If no galleries, return true
    if (galleryContents.length === 0) {
        return true;
    }

    let allSuccess = true;

    for (const galleryContent of galleryContents) {
        const galleryRef = galleryRefs.value[galleryContent.content_id];

        if (galleryRef?.saveChanges) {
            try {
                const result = await galleryRef.saveChanges();
                if (!result) {
                    allSuccess = false;
                }
            } catch (error) {
                console.error(`Failed to save gallery ${galleryContent.content_id}:`, error);
                allSuccess = false;
            }
        }
    }

    return allSuccess;
};

// Expose methods for parent component
defineExpose({
    saveAllGalleries,
});
</script>

<template>
    <div class="space-y-4">
        <!-- Indicateur de modifications non sauvegardées -->
        <div
            v-if="hasUnsavedChanges"
            class="flex items-center gap-2 rounded-md bg-yellow-50 p-3 text-sm text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-200"
        >
            <div class="h-2 w-2 animate-pulse rounded-full bg-yellow-500"></div>
            <span>Modifications en cours de sauvegarde... Ne fermez pas cette page.</span>
        </div>

        <!-- Add Content Buttons -->
        <div class="flex gap-2">
            <Button
                v-for="contentType in contentTypes"
                :key="contentType.value"
                type="button"
                variant="outline"
                size="sm"
                :data-testid="contentType.value === 'markdown' ? 'add-text-button' : `add-${contentType.value}-button`"
                @click="addContent(contentType.value)"
            >
                <component :is="contentType.icon" class="mr-2 h-4 w-4" />
                Ajouter {{ contentType.label }}
            </Button>
        </div>

        <!-- Content Blocks -->
        <div ref="contentListRef" class="space-y-4">
            <Card v-for="(content, index) in localContents" :key="`${content.id}-${index}`" class="relative">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <div class="flex items-center gap-2">
                        <GripVertical class="drag-handle text-muted-foreground h-5 w-5 cursor-move" />
                        <CardTitle class="text-sm">
                            {{ getContentTypeLabel(getContentTypeFromClass(content.content_type)) }}
                        </CardTitle>
                    </div>
                    <Button type="button" variant="ghost" size="sm" @click="removeContent(index)">
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </CardHeader>
                <CardContent>
                    <!-- Markdown Content -->
                    <div v-if="getContentTypeFromClass(content.content_type) === 'markdown'" class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label>Contenu Markdown ({{ locale.toUpperCase() }})</Label>
                            <!-- Indicateur de sauvegarde -->
                            <div class="flex items-center gap-2 text-xs">
                                <div v-if="savingStatus[content.content_id] === 'saving'" class="flex items-center gap-1 text-blue-600">
                                    <div class="h-3 w-3 animate-spin rounded-full border-b-2 border-blue-600"></div>
                                    Sauvegarde...
                                </div>
                                <div v-else-if="savingStatus[content.content_id] === 'saved'" class="text-green-600">✓ Sauvegardé</div>
                                <div v-else-if="savingStatus[content.content_id] === 'error'" class="text-red-600">⚠ Erreur de sauvegarde</div>
                            </div>
                        </div>
                        <MarkdownEditor
                            :model-value="
                                contentCache[content.content_id] ??
                                (content.content?.translation_key?.translations?.find((t) => t.locale === locale)?.text || '')
                            "
                            placeholder="Écrivez votre contenu en Markdown..."
                            :data-testid="`markdown-textarea-${content.content_id}`"
                            @update:model-value="(value: string) => updateMarkdownContent(content.content_id, value)"
                        />
                        <p class="text-muted-foreground mt-2 text-xs">
                            Utilisez la syntaxe Markdown et insérez des emojis avec le bouton <span class="font-semibold">😊</span> ou en tapant <code class="bg-muted rounded px-1">:emoji_name:</code>
                        </p>
                    </div>

                    <!-- Gallery Content -->
                    <div v-if="getContentTypeFromClass(content.content_type) === 'gallery'" class="space-y-2">
                        <ContentGalleryManager
                            :ref="
                                (el: InstanceType<typeof ContentGalleryManager> | null) => {
                                    if (el) galleryRefs[content.content_id] = el;
                                }
                            "
                            :gallery-id="content.content_id"
                            :initial-images="transformGalleryImages(content.content)"
                            :locale="locale"
                            @update:images="(images) => updateGalleryComplete(content.content_id, images)"
                        />
                    </div>

                    <!-- Video Content -->
                    <div v-if="getContentTypeFromClass(content.content_type) === 'video'" class="space-y-2">
                        <ContentVideoManager
                            :blog-content-video-id="content.content.id"
                            :locale="locale"
                            @video-selected="() => {}"
                            @video-removed="() => {}"
                        />
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Empty State -->
        <div v-if="localContents.length === 0" class="text-muted-foreground py-8 text-center">
            <p>Aucun contenu ajouté pour le moment.</p>
            <p class="mt-2 text-sm">Utilisez les boutons ci-dessus pour ajouter du contenu.</p>
        </div>
    </div>
</template>
