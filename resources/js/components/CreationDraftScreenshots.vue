<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import PictureInput from '@/components/PictureInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import axios from 'axios';
import { Loader2, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';

interface Screenshot {
    id: number;
    creation_draft_id: number;
    picture_id: number;
    caption_translation_key_id: number | null;
    created_at: string;
    updated_at: string;
    picture: {
        id: number;
        filename: string;
        path_original: string;
    };
    caption_translation_key?: {
        id: number;
        key: string;
        translations: {
            id: number;
            translation_key_id: number;
            locale: string;
            text: string;
        }[];
    };
}

const props = defineProps<{
    creationDraftId: number | null;
    locale: string;
}>();

const screenshots = ref<Screenshot[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const isAddModalOpen = ref(false);
const isEditModalOpen = ref(false);
const selectedScreenshot = ref<Screenshot | null>(null);
const newScreenshotPictureId = ref<number | undefined>(undefined);
const newScreenshotCaption = ref('');
const editScreenshotCaption = ref('');

// Récupérer la liste des screenshots
const fetchScreenshots = async () => {
    if (!props.creationDraftId) return;

    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get(
            route('dashboard.api.creation-drafts.draft-screenshots.index', {
                creation_draft: props.creationDraftId,
            }),
        );
        screenshots.value = response.data;
    } catch (err) {
        error.value = "Erreur lors du chargement des captures d'écran";
        console.error(err);
    } finally {
        loading.value = false;
    }
};

// Ajouter un nouveau screenshot
const addScreenshot = async () => {
    if (!newScreenshotPictureId.value || !props.creationDraftId) {
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        const data = {
            picture_id: newScreenshotPictureId.value,
            locale: props.locale,
            caption: '',
        };

        if (newScreenshotCaption.value) {
            data.caption = newScreenshotCaption.value;
        }

        await axios.post(
            route('dashboard.api.creation-drafts.draft-screenshots.store', {
                creation_draft: props.creationDraftId,
            }),
            data,
        );

        await fetchScreenshots();
        resetNewScreenshotForm();
        isAddModalOpen.value = false;
    } catch (err) {
        error.value = "Erreur lors de l'ajout de la capture d'écran";
        console.error(err);
    } finally {
        loading.value = false;
    }
};

// Mettre à jour un screenshot existant
const updateScreenshot = async () => {
    if (!selectedScreenshot.value) return;

    loading.value = true;
    error.value = null;

    try {
        await axios.put(
            route('dashboard.api.draft-screenshots.update', {
                draft_screenshot: selectedScreenshot.value.id,
            }),
            {
                locale: props.locale,
                caption: editScreenshotCaption.value,
            },
        );

        await fetchScreenshots();
        isEditModalOpen.value = false;
    } catch (err) {
        error.value = 'Erreur lors de la mise à jour de la description';
        console.error(err);
    } finally {
        loading.value = false;
    }
};

// Supprimer un screenshot
const deleteScreenshot = async (screenshot: Screenshot) => {
    if (!confirm("Êtes-vous sûr de vouloir supprimer cette capture d'écran ?")) {
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        await axios.delete(
            route('dashboard.api.draft-screenshots.destroy', {
                draft_screenshot: screenshot.id,
            }),
        );

        await fetchScreenshots();
    } catch (err) {
        error.value = "Erreur lors de la suppression de la capture d'écran";
        console.error(err);
    } finally {
        loading.value = false;
    }
};

// Ouvrir le modal d'édition pour un screenshot
const openEditModal = (screenshot: Screenshot) => {
    selectedScreenshot.value = screenshot;

    const captionTranslation = screenshot.caption_translation_key?.translations.find((t) => t.locale === props.locale);

    editScreenshotCaption.value = captionTranslation?.text || '';
    isEditModalOpen.value = true;
};

// Réinitialiser le formulaire d'ajout de screenshot
const resetNewScreenshotForm = () => {
    newScreenshotPictureId.value = undefined;
    newScreenshotCaption.value = '';
};

// Récupérer la description d'un screenshot pour la locale actuelle
const getScreenshotCaption = (screenshot: Screenshot): string => {
    if (!screenshot.caption_translation_key) return '';

    const translation = screenshot.caption_translation_key.translations.find((t) => t.locale === props.locale);

    return translation?.text || '';
};

// Charger les screenshots au montage du composant
onMounted(() => {
    if (props.creationDraftId) {
        fetchScreenshots();
    }
});

// Surveiller les changements des props pour recharger les données si nécessaire
watch([() => props.creationDraftId, () => props.locale], async ([newDraftId, newLocale], [oldDraftId, oldLocale]) => {
    if (newDraftId && (newDraftId !== oldDraftId || newLocale !== oldLocale)) {
        await fetchScreenshots();
    }
});
</script>

<template>
    <div class="space-y-6">
        <HeadingSmall title="Captures d'écran" description="Ajoutez des captures d'écran pour illustrer votre création." />

        <!-- Message d'erreur -->
        <div v-if="error" class="mb-4 rounded-md bg-destructive/10 p-4 text-sm text-destructive">
            {{ error }}
        </div>

        <div v-if="!props.creationDraftId" class="rounded-md bg-muted p-4 text-sm text-muted-foreground">
            Veuillez d'abord enregistrer le brouillon pour pouvoir ajouter des captures d'écran.
        </div>

        <div v-else>
            <!-- Grille des screenshots -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <!-- Carte pour ajouter un nouveau screenshot -->
                <Card class="cursor-pointer border-dashed transition-colors hover:bg-muted/50" @click="isAddModalOpen = true">
                    <CardContent class="flex h-full min-h-[200px] flex-col items-center justify-center p-6">
                        <Plus class="mb-2 h-12 w-12 text-muted-foreground" />
                        <p class="text-sm text-muted-foreground">Ajouter une capture d'écran</p>
                    </CardContent>
                </Card>

                <!-- Screenshots existants -->
                <Card v-for="screenshot in screenshots" :key="screenshot.id" class="overflow-hidden">
                    <div class="relative aspect-square bg-muted">
                        <img
                            :src="`/storage/${screenshot.picture.path_original}`"
                            :alt="getScreenshotCaption(screenshot)"
                            class="h-full w-full object-cover"
                        />
                    </div>
                    <CardContent class="p-4">
                        <div class="flex items-start justify-between">
                            <div class="min-w-0 flex-1">
                                <h3 class="truncate text-sm font-medium">{{ screenshot.picture.filename }}</h3>
                                <p class="mt-1 line-clamp-2 text-xs text-muted-foreground">
                                    {{ getScreenshotCaption(screenshot) || 'Aucune description' }}
                                </p>
                            </div>
                            <div class="ml-2 flex flex-shrink-0 space-x-1">
                                <Button variant="ghost" size="icon" @click.stop="openEditModal(screenshot)" title="Modifier la description">
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button variant="ghost" size="icon" @click.stop="deleteScreenshot(screenshot)" title="Supprimer">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- État vide -->
            <div v-if="screenshots.length === 0 && !loading" class="py-8 text-center text-muted-foreground">
                <p>Aucune capture d'écran ajoutée</p>
                <Button variant="outline" class="mt-4" @click="isAddModalOpen = true"> Ajouter une capture d'écran </Button>
            </div>

            <!-- État de chargement -->
            <div v-if="loading" class="flex justify-center py-8">
                <Loader2 class="h-8 w-8 animate-spin text-primary" />
            </div>
        </div>

        <!-- Modal d'ajout de screenshot -->
        <Dialog v-model:open="isAddModalOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Ajouter une capture d'écran</DialogTitle>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Image</label>
                        <PictureInput v-model="newScreenshotPictureId" />
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Description ({{ props.locale }})</label>
                        <Input v-model="newScreenshotCaption" placeholder="Description de la capture d'écran" />
                        <p class="text-xs text-muted-foreground">La description peut être laissée vide. Elle pourra être ajoutée ultérieurement.</p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="isAddModalOpen = false" :disabled="loading">Annuler</Button>
                    <Button :disabled="!newScreenshotPictureId || loading" @click="addScreenshot">
                        <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                        Ajouter
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Modal d'édition de screenshot -->
        <Dialog v-model:open="isEditModalOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Modifier la description</DialogTitle>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div v-if="selectedScreenshot" class="relative aspect-video w-full overflow-hidden rounded-lg bg-muted">
                        <img
                            :src="`/storage/${selectedScreenshot.picture.path_original}`"
                            :alt="selectedScreenshot.picture.filename"
                            class="h-full w-full object-contain"
                        />
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Description ({{ props.locale }})</label>
                        <Input v-model="editScreenshotCaption" placeholder="Description de la capture d'écran" />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="isEditModalOpen = false" :disabled="loading">Annuler</Button>
                    <Button :disabled="loading" @click="updateScreenshot">
                        <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                        Enregistrer
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
