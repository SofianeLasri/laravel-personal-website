<script setup lang="ts">
import PictureInput from '@/components/dashboard/PictureInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useRoute } from '@/composables/useRoute';
import type { Picture, Video } from '@/types';
import axios from 'axios';
import { GripVertical, Image, Text, Trash2, Video as VideoIcon } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref, watchEffect } from 'vue';
import { toast } from 'vue-sonner';
import Sortable from 'sortablejs';

interface BlogContent {
    id?: number;
    content_type: string;
    content_id: number;
    order: number;
    content?: {
        id: number;
        translation_key?: {
            translations: Array<{
                locale: string;
                text: string;
            }>;
        };
    };
}

interface Props {
    draftId: number; // Now required since we only show this component when draft exists
    contents: BlogContent[];
    pictures: Picture[];
    videos: Video[];
    locale: 'fr' | 'en';
}

const props = defineProps<Props>();
const route = useRoute();

const localContents = ref<BlogContent[]>([...props.contents]);
const sortableInstance = ref<Sortable | null>(null);
const contentListRef = ref<HTMLElement | null>(null);

// Cache local pour les contenus en cours d'édition
const contentCache = ref<Record<number, string>>({});
const savingStatus = ref<Record<number, 'idle' | 'saving' | 'saved' | 'error'>>({});
const saveTimeouts = ref<Record<number, ReturnType<typeof setTimeout>>>({});

// Initialiser le cache avec les contenus existants
const initializeContentCache = () => {
    console.log('Initializing content cache for', localContents.value.length, 'contents');
    localContents.value.forEach((content) => {
        if (getContentTypeFromClass(content.content_type) === 'markdown') {
            const currentText = content.content?.translation_key?.translations?.find((t) => t.locale === props.locale)?.text ?? '';
            console.log(`Content ${content.content_id}: found text "${currentText}" for locale ${props.locale}`);
            contentCache.value[content.content_id] = currentText;
            savingStatus.value[content.content_id] = 'idle';
        }
    });
    console.log('Content cache initialized:', contentCache.value);
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
        let contentId: number;

        // Create the content based on type
        if (type === 'markdown') {
            // Create a new markdown content
            const response = await axios.post(route('dashboard.api.blog-content-markdown.store'), {
                content: '',
                locale: props.locale,
            });
            contentId = response.data.id;
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
        } else if (type === 'video') {
            // For video, we'll need to select an existing video first
            toast.info('Sélectionnez une vidéo dans la liste');
            return;
        } else {
            return;
        }

        // Add to blog post draft contents
        const response = await axios.post(route('dashboard.api.blog-post-draft-contents.store'), {
            blog_post_draft_id: props.draftId,
            content_type:
                type === 'markdown'
                    ? 'App\\Models\\BlogContentMarkdown'
                    : type === 'gallery'
                      ? 'App\\Models\\BlogContentGallery'
                      : 'App\\Models\\BlogContentVideo',
            content_id: contentId,
            order: localContents.value.length + 1,
        });

        localContents.value.push(response.data);

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

const updateGalleryImages = async (contentId: number, pictureIds: number[]) => {
    try {
        await axios.put(
            route('dashboard.api.blog-content-galleries.update-pictures', {
                blog_content_gallery: contentId,
            }),
            {
                picture_ids: pictureIds,
            },
        );
        toast.success('Images mises à jour');
    } catch (error: unknown) {
        console.error('Erreur lors de la mise à jour des images:', error);
        toast.error('Erreur lors de la mise à jour');
    }
};

const updateVideoContent = async (contentId: number, videoId: number) => {
    try {
        await axios.put(
            route('dashboard.api.blog-content-videos.update', {
                blog_content_video: contentId,
            }),
            {
                video_id: videoId,
            },
        );
        toast.success('Vidéo mise à jour');
    } catch (error: unknown) {
        console.error('Erreur lors de la mise à jour:', error);
        toast.error('Erreur lors de la mise à jour');
    }
};

const getContentTypeLabel = (type: string) => {
    const contentType = contentTypes.find((t) => t.value === type);
    return contentType?.label ?? type;
};

const getContentTypeFromClass = (className: string): string => {
    if (className.includes('BlogContentMarkdown')) return 'markdown';
    if (className.includes('BlogContentGallery')) return 'gallery';
    if (className.includes('BlogContentVideo')) return 'video';
    return 'unknown';
};
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
                        <Textarea
                            :value="
                                contentCache[content.content_id] ??
                                (content.content?.translation_key?.translations?.find((t) => t.locale === locale)?.text || '')
                            "
                            placeholder="Écrivez votre contenu en Markdown..."
                            class="min-h-[200px] font-mono"
                            :data-testid="`markdown-textarea-${content.content_id}`"
                            @input="(e: any) => updateMarkdownContent(content.content_id, e.target.value)"
                        />
                        <p class="text-muted-foreground text-xs">
                            Vous pouvez utiliser la syntaxe Markdown : **gras**, *italique*, [lien](url), etc.
                        </p>
                    </div>

                    <!-- Gallery Content -->
                    <div v-if="getContentTypeFromClass(content.content_type) === 'gallery'" class="space-y-2">
                        <Label>Images de la galerie</Label>
                        <div class="text-muted-foreground mb-2 text-sm">
                            Sélectionnez une ou plusieurs images. Une seule image sera affichée en grand, plusieurs seront en galerie.
                        </div>
                        <PictureInput
                            :picture-id="null"
                            :multiple="true"
                            label="Sélectionner les images"
                            @update:picture-ids="(ids: number[]) => updateGalleryImages(content.content_id, ids)"
                        />
                    </div>

                    <!-- Video Content -->
                    <div v-if="getContentTypeFromClass(content.content_type) === 'video'" class="space-y-2">
                        <Label>Sélectionner une vidéo</Label>
                        <Select
                            :value="content.content_id?.toString()"
                            @update:model-value="(value) => updateVideoContent(content.id!, parseInt(String(value)))"
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Choisir une vidéo" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="video in videos" :key="video.id" :value="video.id.toString()">
                                    {{ video.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
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