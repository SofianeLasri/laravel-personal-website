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
import { onMounted, ref } from 'vue';
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
    draftId?: number;
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

// Initialize sortable on mount
onMounted(() => {
    if (contentListRef.value) {
        sortableInstance.value = Sortable.create(contentListRef.value, {
            animation: 150,
            handle: '.drag-handle',
            onEnd: (evt) => {
                if (evt.oldIndex !== undefined && evt.newIndex !== undefined) {
                    const item = localContents.value.splice(evt.oldIndex, 1)[0];
                    localContents.value.splice(evt.newIndex, 0, item);
                    updateContentOrder();
                }
            },
        });
    }
});

const contentTypes = [
    { value: 'markdown', label: 'Texte (Markdown)', icon: Text },
    { value: 'gallery', label: 'Image(s) / Galerie', icon: Image },
    { value: 'video', label: 'Vidéo', icon: VideoIcon },
];

const addContent = async (type: string) => {
    if (!props.draftId) {
        toast.error("Veuillez d'abord sauvegarder le brouillon");
        return;
    }

    try {
        let contentId: number;

        // Create the content based on type
        if (type === 'markdown') {
            // Create a new markdown content
            const response = await axios.post(route('dashboard.api.blog-content-markdown.store'), {
                text_fr: '',
                text_en: '',
            });
            contentId = response.data.id;
        } else if (type === 'gallery') {
            // Create a new gallery content
            const response = await axios.post(route('dashboard.api.blog-content-galleries.store'), {
                layout: 'grid',
                columns: 2,
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
            order: localContents.value.length,
        });

        localContents.value.push(response.data);
        toast.success('Bloc de contenu ajouté');
    } catch (error) {
        console.error("Erreur lors de l'ajout du contenu:", error);
        toast.error("Erreur lors de l'ajout du contenu");
    }
};

const removeContent = async (index: number) => {
    const content = localContents.value[index];

    if (!content.id || !props.draftId) {
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
    } catch (error) {
        console.error('Erreur lors de la suppression:', error);
        toast.error('Erreur lors de la suppression');
    }
};

const updateContentOrder = async () => {
    if (!props.draftId) return;

    // Update local order
    localContents.value.forEach((content, index) => {
        content.order = index;
    });

    try {
        await axios.post(route('dashboard.api.blog-post-draft-contents.reorder'), {
            blog_post_draft_id: props.draftId,
            contents: localContents.value.map((c, index) => ({
                id: c.id,
                order: index,
            })),
        });
    } catch (error) {
        console.error('Erreur lors de la réorganisation:', error);
        toast.error('Erreur lors de la réorganisation');
    }
};

const updateMarkdownContent = async (contentId: number, text: string) => {
    try {
        await axios.put(
            route('dashboard.api.blog-content-markdown.update', {
                blog_content_markdown: contentId,
            }),
            {
                [`text_${props.locale}`]: text,
            },
        );
    } catch (error) {
        console.error('Erreur lors de la mise à jour:', error);
    }
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
    } catch (error) {
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
    } catch (error) {
        console.error('Erreur lors de la mise à jour:', error);
        toast.error('Erreur lors de la mise à jour');
    }
};

const getContentTypeLabel = (type: string) => {
    const contentType = contentTypes.find((t) => t.value === type);
    return contentType?.label || type;
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
        <!-- Add Content Buttons -->
        <div class="flex gap-2">
            <Button
                v-for="contentType in contentTypes"
                :key="contentType.value"
                type="button"
                variant="outline"
                size="sm"
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
                        <Label>Contenu Markdown ({{ locale.toUpperCase() }})</Label>
                        <Textarea
                            :value="content.content?.translation_key?.translations?.find((t) => t.locale === locale)?.text || ''"
                            @input="(e: any) => updateMarkdownContent(content.content_id, e.target.value)"
                            placeholder="Écrivez votre contenu en Markdown..."
                            class="min-h-[200px] font-mono"
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
                            @update:picture-ids="(ids: number[]) => updateGalleryImages(content.content_id, ids)"
                            label="Sélectionner les images"
                        />
                    </div>

                    <!-- Video Content -->
                    <div v-if="getContentTypeFromClass(content.content_type) === 'video'" class="space-y-2">
                        <Label>Sélectionner une vidéo</Label>
                        <Select
                            :value="content.content_id?.toString()"
                            @update:model-value="(value: string) => updateVideoContent(content.id!, parseInt(value))"
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