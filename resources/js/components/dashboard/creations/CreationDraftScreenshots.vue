<script setup lang="ts">
import PictureInput from '@/components/dashboard/media/PictureInput.vue';
import HeadingSmall from '@/components/dashboard/shared/ui/HeadingSmall.vue';
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
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useRoute } from '@/composables/useRoute';
import { Screenshot } from '@/types';
import axios from 'axios';
import { ChevronDown, ChevronUp, Loader2, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

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
const isDeleteDialogOpen = ref(false);
const selectedScreenshot = ref<Screenshot | null>(null);
const screenshotToDelete = ref<Screenshot | null>(null);
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

const confirmDeleteScreenshot = (screenshot: Screenshot) => {
    screenshotToDelete.value = screenshot;
    isDeleteDialogOpen.value = true;
};

const deleteScreenshot = async () => {
    if (!screenshotToDelete.value) return;

    loading.value = true;
    error.value = null;
    isDeleteDialogOpen.value = false;

    try {
        await axios.delete(
            route('dashboard.api.draft-screenshots.destroy', {
                draft_screenshot: screenshotToDelete.value.id,
            }),
        );

        await fetchScreenshots();
        screenshotToDelete.value = null;
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

    editScreenshotCaption.value = captionTranslation?.text ?? '';
    isEditModalOpen.value = true;
};

const resetNewScreenshotForm = () => {
    newScreenshotPictureId.value = undefined;
    newScreenshotCaption.value = '';
};

const getScreenshotCaption = (screenshot: Screenshot): string => {
    if (!screenshot.caption_translation_key) return '';

    const translation = screenshot.caption_translation_key.translations.find((t) => t.locale === props.locale);

    return translation?.text ?? '';
};

// Reordering functionality
const sortedScreenshots = computed(() => {
    return [...screenshots.value].sort((a, b) => a.order - b.order);
});

const reorderScreenshots = async (newOrder: Screenshot[]) => {
    if (!props.creationDraftId) return;

    loading.value = true;
    error.value = null;

    // Create backup for rollback
    const backup = [...screenshots.value];

    // Optimistic UI update
    screenshots.value = newOrder;

    try {
        const reorderData = newOrder.map((screenshot, index) => ({
            id: screenshot.id,
            order: index + 1,
        }));

        const response = await axios.put(
            route('dashboard.api.creation-drafts.draft-screenshots.reorder', {
                creation_draft: props.creationDraftId,
            }),
            { screenshots: reorderData },
        );

        screenshots.value = response.data;
    } catch (err) {
        // Rollback on error
        screenshots.value = backup;
        error.value = "Erreur lors du réordonnancement des captures d'écran";
        console.error(err);
    } finally {
        loading.value = false;
    }
};

const moveUp = async (screenshot: Screenshot) => {
    const currentIndex = sortedScreenshots.value.findIndex((s) => s.id === screenshot.id);
    if (currentIndex <= 0) return;

    const newOrder = [...sortedScreenshots.value];
    [newOrder[currentIndex - 1], newOrder[currentIndex]] = [newOrder[currentIndex], newOrder[currentIndex - 1]];

    await reorderScreenshots(newOrder);
};

const moveDown = async (screenshot: Screenshot) => {
    const currentIndex = sortedScreenshots.value.findIndex((s) => s.id === screenshot.id);
    if (currentIndex >= sortedScreenshots.value.length - 1) return;

    const newOrder = [...sortedScreenshots.value];
    [newOrder[currentIndex], newOrder[currentIndex + 1]] = [newOrder[currentIndex + 1], newOrder[currentIndex]];

    await reorderScreenshots(newOrder);
};

const changeOrder = async (screenshot: Screenshot, newPosition: number) => {
    const totalScreenshots = sortedScreenshots.value.length;

    // Validate position
    if (newPosition < 1 || newPosition > totalScreenshots) {
        return;
    }

    const currentIndex = sortedScreenshots.value.findIndex((s) => s.id === screenshot.id);
    const newIndex = newPosition - 1;

    if (currentIndex === newIndex) return;

    const newOrder = [...sortedScreenshots.value];
    const [movedItem] = newOrder.splice(currentIndex, 1);
    newOrder.splice(newIndex, 0, movedItem);

    await reorderScreenshots(newOrder);
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

                <Card v-for="screenshot in sortedScreenshots" :key="screenshot.id" class="gap-0 overflow-hidden py-0">
                    <div class="bg-muted relative aspect-video">
                        <img
                            :src="`/storage/${screenshot.picture.path_original}`"
                            :alt="getScreenshotCaption(screenshot)"
                            class="h-full w-full object-cover"
                        />
                        <!-- Order controls overlay -->
                        <div class="bg-background/90 absolute top-2 right-2 flex items-center gap-1 rounded-md p-1 shadow-md backdrop-blur-sm">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-6 w-6"
                                :disabled="screenshot.order === 1 || loading"
                                title="Monter"
                                @click.stop="moveUp(screenshot)"
                            >
                                <ChevronUp class="h-4 w-4" />
                            </Button>
                            <Input
                                :model-value="screenshot.order"
                                type="number"
                                :min="1"
                                :max="sortedScreenshots.length"
                                class="h-6 w-12 p-1 text-center text-xs"
                                :disabled="loading"
                                @blur="
                                    (e: Event) => {
                                        const target = e.target as HTMLInputElement | null;
                                        if (target) changeOrder(screenshot, parseInt(target.value));
                                    }
                                "
                                @keyup.enter="
                                    (e: KeyboardEvent) => {
                                        const target = e.target as HTMLInputElement | null;
                                        if (target) target.blur();
                                    }
                                "
                            />
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-6 w-6"
                                :disabled="screenshot.order === sortedScreenshots.length || loading"
                                title="Descendre"
                                @click.stop="moveDown(screenshot)"
                            >
                                <ChevronDown class="h-4 w-4" />
                            </Button>
                        </div>
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
                                <Button variant="ghost" size="icon" title="Supprimer" @click.stop="confirmDeleteScreenshot(screenshot)">
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

        <!-- Delete Screenshot Confirmation Dialog -->
        <AlertDialog v-model:open="isDeleteDialogOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Êtes-vous sûr de vouloir supprimer cette capture d'écran ?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Cette action est irréversible. La capture d'écran sera supprimée définitivement.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Annuler</AlertDialogCancel>
                    <AlertDialogAction class="bg-destructive text-destructive-foreground hover:bg-destructive/90" @click="deleteScreenshot">
                        <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                        Supprimer
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
