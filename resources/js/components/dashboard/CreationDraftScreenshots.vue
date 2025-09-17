<script setup lang="ts">
import HeadingSmall from '@/components/dashboard/HeadingSmall.vue';
import PictureInput from '@/components/dashboard/PictureInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useRoute } from '@/composables/useRoute';
import { Screenshot } from '@/types';
import axios from 'axios';
import { Loader2, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';

const props = defineProps<{
    creationDraftId: number | null;
    locale: string;
}>();
const route = useRoute();

const screenshots = ref<Screenshot[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const isAddModalOpen = ref(false);
const isEditModalOpen = ref(false);
const selectedScreenshot = ref<Screenshot | null>(null);
const newScreenshotPictureId = ref<number | undefined>(undefined);
const newScreenshotCaption = ref('');
const editScreenshotCaption = ref('');

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

const openEditModal = (screenshot: Screenshot) => {
    selectedScreenshot.value = screenshot;

    const captionTranslation = screenshot.caption_translation_key?.translations.find((t) => t.locale === props.locale);

    editScreenshotCaption.value = captionTranslation?.text || '';
    isEditModalOpen.value = true;
};

const resetNewScreenshotForm = () => {
    newScreenshotPictureId.value = undefined;
    newScreenshotCaption.value = '';
};

const getScreenshotCaption = (screenshot: Screenshot): string => {
    if (!screenshot.caption_translation_key) return '';

    const translation = screenshot.caption_translation_key.translations.find((t) => t.locale === props.locale);

    return translation?.text || '';
};

onMounted(() => {
    if (props.creationDraftId) {
        void fetchScreenshots();
    }
});

watch([() => props.creationDraftId, () => props.locale], async ([newDraftId, newLocale], [oldDraftId, oldLocale]) => {
    if (newDraftId && (newDraftId !== oldDraftId || newLocale !== oldLocale)) {
        await fetchScreenshots();
    }
});
</script>

<template>
    <div class="space-y-6">
        <HeadingSmall title="Captures d'écran" description="Ajoutez des captures d'écran pour illustrer votre création." />

        <div v-if="error" class="bg-destructive/10 text-destructive mb-4 rounded-md p-4 text-sm">
            {{ error }}
        </div>

        <div v-if="!props.creationDraftId" class="bg-muted text-muted-foreground rounded-md p-4 text-sm">
            Veuillez d'abord enregistrer le brouillon pour pouvoir ajouter des captures d'écran.
        </div>

        <div v-else>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card class="hover:bg-muted/50 cursor-pointer border-dashed transition-colors" @click="isAddModalOpen = true">
                    <CardContent class="flex h-full min-h-[200px] flex-col items-center justify-center p-6">
                        <Plus class="text-muted-foreground mb-2 h-12 w-12" />
                        <p class="text-muted-foreground text-sm">Ajouter une capture d'écran</p>
                    </CardContent>
                </Card>

                <Card v-for="screenshot in screenshots" :key="screenshot.id" class="gap-0 overflow-hidden py-0">
                    <div class="bg-muted relative aspect-video">
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
                                <p class="text-muted-foreground mt-1 line-clamp-2 text-xs">
                                    {{ getScreenshotCaption(screenshot) || 'Aucune description' }}
                                </p>
                            </div>
                            <div class="ml-2 flex flex-shrink-0 space-x-1">
                                <Button variant="ghost" size="icon" title="Modifier la description" @click.stop="openEditModal(screenshot)">
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button variant="ghost" size="icon" title="Supprimer" @click.stop="deleteScreenshot(screenshot)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div v-if="screenshots.length === 0 && !loading" class="text-muted-foreground py-8 text-center">
                <p>Aucune capture d'écran ajoutée</p>
                <Button variant="outline" class="mt-4" @click="isAddModalOpen = true"> Ajouter une capture d'écran </Button>
            </div>

            <div v-if="loading" class="flex justify-center py-8">
                <Loader2 class="text-primary h-8 w-8 animate-spin" />
            </div>
        </div>

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
                        <Input v-model="newScreenshotCaption" placeholder="Description de la capture d'écran" data-form-type="other" />
                        <p class="text-muted-foreground text-xs">La description peut être laissée vide. Elle pourra être ajoutée ultérieurement.</p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" :disabled="loading" @click="isAddModalOpen = false">Annuler</Button>
                    <Button :disabled="!newScreenshotPictureId || loading" @click="addScreenshot">
                        <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                        Ajouter
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="isEditModalOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Modifier la description</DialogTitle>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div v-if="selectedScreenshot" class="bg-muted relative aspect-video w-full overflow-hidden rounded-lg">
                        <img
                            :src="`/storage/${selectedScreenshot.picture.path_original}`"
                            :alt="selectedScreenshot.picture.filename"
                            class="h-full w-full object-contain"
                        />
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Description ({{ props.locale }})</label>
                        <Input v-model="editScreenshotCaption" placeholder="Description de la capture d'écran" data-form-type="other" />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" :disabled="loading" @click="isEditModalOpen = false">Annuler</Button>
                    <Button :disabled="loading" @click="updateScreenshot">
                        <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                        Enregistrer
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
