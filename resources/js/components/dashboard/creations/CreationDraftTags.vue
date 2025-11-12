<script setup lang="ts">
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
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { useRoute } from '@/composables/useRoute';
import { Tag } from '@/types';
import axios from 'axios';
import { Loader2, Minus, Pencil, Plus, Search, Tag as TagIcon, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    creationDraftId: number | null;
}>();
const route = useRoute();

const loading = ref(false);
const error = ref<string | null>(null);
const searchQuery = ref('');
const modalOpen = ref(false);
const allTags = ref<Tag[]>([]);
const associatedTags = ref<Tag[]>([]);

const isAddTagDialogOpen = ref(false);
const isEditTagDialogOpen = ref(false);
const isDeleteDialogOpen = ref(false);
const newTagName = ref('');
const editTagId = ref<number | null>(null);
const editTagName = ref('');
const tagToDelete = ref<Tag | null>(null);
const tagHasAssociations = ref(false);
const associationsDetails = ref<{ creations_count: number; creation_drafts_count: number }>({
    creations_count: 0,
    creation_drafts_count: 0,
});

const filteredTags = computed(() => {
    if (!searchQuery.value.trim()) return allTags.value;

    const query = searchQuery.value.toLowerCase();
    return allTags.value.filter((tag) => tag.name.toLowerCase().includes(query) || tag.slug.toLowerCase().includes(query));
});

const fetchAllTags = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get(route('dashboard.api.tags.index'));
        allTags.value = response.data;
    } catch (err) {
        error.value = 'Erreur lors du chargement des tags';
        console.error(err);
    } finally {
        loading.value = false;
    }
};

const fetchAssociatedTags = async () => {
    if (!props.creationDraftId) return;

    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get(
            route('dashboard.api.creation-drafts.tags', {
                creation_draft: props.creationDraftId,
            }),
        );

        associatedTags.value = response.data;
    } catch (err) {
        error.value = 'Erreur lors du chargement des tags associés';
        console.error(err);
    } finally {
        loading.value = false;
    }
};

const isTagAssociated = (tagId: number): boolean => {
    return associatedTags.value.some((t) => t.id === tagId);
};

const associateTag = async (tagId: number) => {
    if (!props.creationDraftId) return;

    loading.value = true;
    error.value = null;

    try {
        await axios.post(
            route('dashboard.api.creation-drafts.attach-tag', {
                creation_draft: props.creationDraftId,
            }),
            {
                tag_id: tagId,
            },
        );

        await fetchAssociatedTags();
        toast.success('Tag associé avec succès');
    } catch (err) {
        error.value = "Erreur lors de l'association du tag";
        console.error(err);
        toast.error("Impossible d'associer ce tag");
    } finally {
        loading.value = false;
    }
};

const dissociateTag = async (tagId: number) => {
    if (!props.creationDraftId) return;

    loading.value = true;
    error.value = null;

    try {
        await axios.post(
            route('dashboard.api.creation-drafts.detach-tag', {
                creation_draft: props.creationDraftId,
            }),
            {
                tag_id: tagId,
            },
        );

        associatedTags.value = associatedTags.value.filter((t) => t.id !== tagId);
        toast.success('Tag dissocié avec succès');
    } catch (err) {
        error.value = 'Erreur lors de la dissociation du tag';
        console.error(err);
        toast.error('Impossible de dissocier ce tag');
    } finally {
        loading.value = false;
    }
};

const createTag = async () => {
    if (!newTagName.value.trim()) return;

    loading.value = true;
    error.value = null;

    try {
        const response = await axios.post(route('dashboard.api.tags.store'), {
            name: newTagName.value.trim(),
        });

        allTags.value.push(response.data);
        resetTagForm();
        isAddTagDialogOpen.value = false;

        toast.success('Tag créé avec succès');

        if (props.creationDraftId) {
            await associateTag(response.data.id);
        }
    } catch (err) {
        error.value = 'Erreur lors de la création du tag';
        console.error(err);

        let errorMessage = 'Impossible de créer ce tag';
        if (axios.isAxiosError(err) && err.response?.data?.errors?.name) {
            errorMessage = err.response.data.errors.name[0];
        }

        toast.error(errorMessage);
    } finally {
        loading.value = false;
    }
};

const updateTag = async () => {
    if (!editTagId.value || !editTagName.value.trim()) return;

    loading.value = true;
    error.value = null;

    try {
        const response = await axios.put(
            route('dashboard.api.tags.update', {
                tag: editTagId.value,
            }),
            {
                name: editTagName.value.trim(),
            },
        );

        const index = allTags.value.findIndex((t) => t.id === editTagId.value);
        if (index !== -1) {
            allTags.value[index] = response.data;
        }

        const associatedIndex = associatedTags.value.findIndex((t) => t.id === editTagId.value);
        if (associatedIndex !== -1) {
            associatedTags.value[associatedIndex] = response.data;
        }

        resetEditForm();
        isEditTagDialogOpen.value = false;

        toast.success('Tag mis à jour avec succès');
    } catch (err) {
        error.value = 'Erreur lors de la mise à jour du tag';
        console.error(err);

        let errorMessage = 'Impossible de mettre à jour ce tag';
        if (axios.isAxiosError(err) && err.response?.data?.errors?.name) {
            errorMessage = err.response.data.errors.name[0];
        }

        toast.error(errorMessage);
    } finally {
        loading.value = false;
    }
};

const deleteTag = async () => {
    if (!tagToDelete.value) return;

    loading.value = true;
    error.value = null;

    try {
        await axios.delete(
            route('dashboard.api.tags.destroy', {
                tag: tagToDelete.value.id,
            }),
        );

        allTags.value = allTags.value.filter((t) => t.id !== tagToDelete.value?.id);

        associatedTags.value = associatedTags.value.filter((t) => t.id !== tagToDelete.value?.id);

        tagToDelete.value = null;
        isDeleteDialogOpen.value = false;

        toast.success('Tag supprimé avec succès');
    } catch (err) {
        error.value = 'Erreur lors de la suppression du tag';
        console.error(err);
        toast.error('Impossible de supprimer ce tag');
    } finally {
        loading.value = false;
    }
};

const checkTagAssociations = async (tagId: number): Promise<boolean> => {
    try {
        const response = await axios.get(
            route('dashboard.api.tags.check-associations', {
                tag: tagId,
            }),
        );

        associationsDetails.value = {
            creations_count: response.data.creations_count,
            creation_drafts_count: response.data.creation_drafts_count,
        };

        return response.data.has_associations;
    } catch (err) {
        console.error('Erreur lors de la vérification des associations:', err);
        return false;
    }
};

const openEditForm = (tag: Tag) => {
    editTagId.value = tag.id;
    editTagName.value = tag.name;
    isEditTagDialogOpen.value = true;
};

// Ouvrir la boîte de dialogue de suppression
const confirmDeleteTag = async (tag: Tag) => {
    tagToDelete.value = tag;

    tagHasAssociations.value = await checkTagAssociations(tag.id);

    isDeleteDialogOpen.value = true;
};

const resetTagForm = () => {
    newTagName.value = '';
};

const resetEditForm = () => {
    editTagId.value = null;
    editTagName.value = '';
};

onMounted(() => {
    void fetchAllTags();
    if (props.creationDraftId) {
        void fetchAssociatedTags();
    }
});

watch(
    () => props.creationDraftId,
    (newVal) => {
        if (newVal) {
            void fetchAssociatedTags();
        } else {
            associatedTags.value = [];
        }
    },
);
</script>

<template>
    <div class="space-y-6">
        <HeadingSmall title="Tags" description="Gérez les tags associés à cette création pour améliorer sa recherche." />

        <div v-if="error" class="bg-destructive/10 text-destructive mb-4 rounded-md p-4 text-sm">
            {{ error }}
        </div>

        <div v-if="!props.creationDraftId" class="bg-muted text-muted-foreground rounded-md p-4 text-sm">
            Veuillez d'abord enregistrer le brouillon pour pouvoir ajouter des tags.
        </div>

        <div v-else>
            <div class="mb-4">
                <div class="mb-2 flex justify-between">
                    <h3 class="text-sm font-medium">Tags associés</h3>
                    <Button variant="outline" size="sm" @click="modalOpen = true">
                        <Plus class="mr-2 h-4 w-4" />
                        Ajouter
                    </Button>
                </div>

                <div v-if="associatedTags.length === 0" class="bg-muted/30 rounded-md py-8 text-center">
                    <TagIcon class="text-muted-foreground mx-auto h-12 w-12" />
                    <p class="text-muted-foreground mt-2 text-sm">Aucun tag associé</p>
                    <Button variant="outline" class="mt-4" @click="modalOpen = true"> Ajouter des tags </Button>
                </div>

                <div v-else class="flex flex-wrap gap-2">
                    <div v-for="tag in associatedTags" :key="tag.id" class="bg-muted flex items-center space-x-1 rounded-full px-2 py-1 text-sm">
                        <TagIcon class="text-muted-foreground h-3 w-3" />
                        <span>{{ tag.name }}</span>
                        <Button variant="ghost" size="icon" class="h-5 w-5 rounded-full" title="Dissocier" @click="dissociateTag(tag.id)">
                            <Minus class="h-3 w-3" />
                        </Button>
                    </div>
                </div>
            </div>

            <Dialog v-model:open="modalOpen" class="sm:max-w-[500px]">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Gérer les tags</DialogTitle>
                    </DialogHeader>

                    <div class="py-4">
                        <div class="relative mb-4">
                            <Search class="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                            <Input v-model="searchQuery" placeholder="Rechercher un tag..." class="pl-8" data-form-type="other" />
                        </div>

                        <Button variant="outline" class="mb-4 flex w-full items-center justify-center py-2" @click="isAddTagDialogOpen = true">
                            <Plus class="mr-2 h-4 w-4" />
                            Créer un nouveau tag
                        </Button>

                        <ScrollArea class="h-[300px]">
                            <div v-if="loading" class="flex h-[200px] items-center justify-center">
                                <Loader2 class="text-primary h-8 w-8 animate-spin" />
                            </div>

                            <div v-else-if="filteredTags.length === 0" class="py-8 text-center">
                                <p class="text-muted-foreground text-sm">Aucun résultat trouvé</p>
                            </div>

                            <div v-else class="space-y-2">
                                <div
                                    v-for="tag in filteredTags"
                                    :key="tag.id"
                                    class="hover:bg-muted flex items-center justify-between rounded-md p-2"
                                >
                                    <div class="flex items-center space-x-2">
                                        <TagIcon class="text-muted-foreground h-4 w-4" />
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium">{{ tag.name }}</p>
                                            <p class="text-muted-foreground truncate text-xs">{{ tag.slug }}</p>
                                        </div>
                                    </div>

                                    <div class="flex space-x-2">
                                        <Button
                                            v-if="isTagAssociated(tag.id)"
                                            variant="outline"
                                            size="sm"
                                            title="Dissocier"
                                            @click="dissociateTag(tag.id)"
                                        >
                                            Dissocier
                                        </Button>
                                        <Button v-else variant="outline" size="sm" title="Ajouter" @click="associateTag(tag.id)"> Ajouter </Button>

                                        <Button variant="ghost" size="icon" title="Modifier" @click="openEditForm(tag)">
                                            <Pencil class="h-4 w-4" />
                                        </Button>

                                        <Button variant="ghost" size="icon" title="Supprimer" @click="confirmDeleteTag(tag)">
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </ScrollArea>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" @click="modalOpen = false">Fermer</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="isAddTagDialogOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Créer un nouveau tag</DialogTitle>
                    </DialogHeader>

                    <div class="space-y-4 py-4">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Nom</label>
                            <Input v-model="newTagName" placeholder="Nom du tag" data-form-type="other" />
                            <p class="text-muted-foreground text-xs">Le slug sera généré automatiquement.</p>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" :disabled="loading" @click="isAddTagDialogOpen = false">Annuler</Button>
                        <Button :disabled="!newTagName || loading" @click="createTag">
                            <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                            Créer
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="isEditTagDialogOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Modifier un tag</DialogTitle>
                    </DialogHeader>

                    <div class="space-y-4 py-4">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Nom</label>
                            <Input v-model="editTagName" placeholder="Nom du tag" data-form-type="other" />
                            <p class="text-muted-foreground text-xs">Le slug sera mis à jour automatiquement.</p>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" :disabled="loading" @click="isEditTagDialogOpen = false">Annuler</Button>
                        <Button :disabled="!editTagName || loading" @click="updateTag">
                            <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                            Enregistrer
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <AlertDialog v-model:open="isDeleteDialogOpen">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Êtes-vous sûr de vouloir supprimer ce tag ?</AlertDialogTitle>
                        <AlertDialogDescription>
                            <span v-if="tagHasAssociations" class="text-destructive font-medium">
                                Attention : ce tag est associé à {{ associationsDetails.creations_count }} création(s) et
                                {{ associationsDetails.creation_drafts_count }} brouillon(s).
                            </span>
                            <span v-else> Cette action est irréversible. Le tag sera supprimé définitivement de la base de données. </span>
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Annuler</AlertDialogCancel>
                        <AlertDialogAction class="bg-destructive text-destructive-foreground hover:bg-destructive/90" @click="deleteTag">
                            <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                            Supprimer
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    </div>
</template>
