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
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useRoute } from '@/composables/useRoute';
import { Technology } from '@/types';
import axios from 'axios';
import { Code, Loader2, Minus, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    creationDraftId: number | null;
    locale: string;
}>();
const route = useRoute();

const loading = ref(false);
const error = ref<string | null>(null);
const searchQuery = ref('');
const modalOpen = ref(false);
const allTechnologies = ref<Technology[]>([]);
const associatedTechnologies = ref<Technology[]>([]);

const technologyTypes = [
    { value: 'framework', label: 'Framework' },
    { value: 'library', label: 'Bibliothèque' },
    { value: 'language', label: 'Langage' },
    { value: 'other', label: 'Autre' },
];

const isAddTechnologyDialogOpen = ref(false);
const isEditTechnologyDialogOpen = ref(false);
const isDeleteDialogOpen = ref(false);
const newTechnologyName = ref('');
const newTechnologyType = ref('framework');
const newTechnologyIconPictureId = ref<number | undefined>(undefined);
const newTechnologyDescription = ref('');
const editTechnologyId = ref<number | null>(null);
const editTechnologyName = ref('');
const editTechnologyType = ref('');
const editTechnologyIconPictureId = ref<number | undefined>(undefined);
const editTechnologyDescription = ref('');
const technologyToDelete = ref<Technology | null>(null);
const technologyHasAssociations = ref(false);
const associationsDetails = ref<{ creations_count: number; creation_drafts_count: number }>({
    creations_count: 0,
    creation_drafts_count: 0,
});

const filteredTechnologies = computed(() => {
    if (!searchQuery.value.trim()) return allTechnologies.value;

    const query = searchQuery.value.toLowerCase();
    return allTechnologies.value.filter((tech) => tech.name.toLowerCase().includes(query) || tech.type.toLowerCase().includes(query));
});

const fetchAllTechnologies = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get(route('dashboard.api.technologies.index'));
        allTechnologies.value = response.data;
    } catch (err) {
        error.value = 'Erreur lors du chargement des technologies';
        console.error(err);
    } finally {
        loading.value = false;
    }
};

const fetchAssociatedTechnologies = async () => {
    if (!props.creationDraftId) return;

    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get(
            route('dashboard.api.creation-drafts.technologies', {
                creation_draft: props.creationDraftId,
            }),
        );

        associatedTechnologies.value = response.data;
    } catch (err) {
        error.value = 'Erreur lors du chargement des technologies associées';
        console.error(err);
    } finally {
        loading.value = false;
    }
};

const isTechnologyAssociated = (technologyId: number): boolean => {
    return associatedTechnologies.value.some((t) => t.id === technologyId);
};

const associateTechnology = async (technologyId: number) => {
    if (!props.creationDraftId) return;

    loading.value = true;
    error.value = null;

    try {
        await axios.post(
            route('dashboard.api.creation-drafts.attach-technology', {
                creation_draft: props.creationDraftId,
            }),
            {
                technology_id: technologyId,
            },
        );

        await fetchAssociatedTechnologies();
        toast.success('Technologie associée avec succès');
    } catch (err) {
        error.value = "Erreur lors de l'association de la technologie";
        console.error(err);
        toast.error("Impossible d'associer cette technologie");
    } finally {
        loading.value = false;
    }
};

const dissociateTechnology = async (technologyId: number) => {
    if (!props.creationDraftId) return;

    loading.value = true;
    error.value = null;

    try {
        await axios.post(
            route('dashboard.api.creation-drafts.detach-technology', {
                creation_draft: props.creationDraftId,
            }),
            {
                technology_id: technologyId,
            },
        );

        associatedTechnologies.value = associatedTechnologies.value.filter((t) => t.id !== technologyId);
        toast.success('Technologie dissociée avec succès');
    } catch (err) {
        error.value = 'Erreur lors de la dissociation de la technologie';
        console.error(err);
        toast.error('Impossible de dissocier cette technologie');
    } finally {
        loading.value = false;
    }
};

const createTechnology = async () => {
    if (!newTechnologyName.value.trim() || !newTechnologyIconPictureId.value || !newTechnologyDescription.value.trim()) return;

    loading.value = true;
    error.value = null;

    try {
        const response = await axios.post(route('dashboard.api.technologies.store'), {
            name: newTechnologyName.value.trim(),
            type: newTechnologyType.value,
            icon_picture_id: newTechnologyIconPictureId.value,
            locale: props.locale,
            description: newTechnologyDescription.value.trim(),
        });

        allTechnologies.value.push(response.data);
        resetTechnologyForm();
        isAddTechnologyDialogOpen.value = false;

        toast.success('Technologie créée avec succès');

        if (props.creationDraftId) {
            await associateTechnology(response.data.id);
        }
    } catch (err) {
        error.value = 'Erreur lors de la création de la technologie';
        console.error(err);

        let errorMessage = 'Impossible de créer cette technologie';
        if (axios.isAxiosError(err) && err.response?.data?.errors) {
            const errors = err.response.data.errors;
            const firstError = Object.values(errors)[0] as string[];
            if (firstError && firstError.length > 0) {
                errorMessage = firstError[0];
            }
        }

        toast.error(errorMessage);
    } finally {
        loading.value = false;
    }
};

const updateTechnology = async () => {
    if (!editTechnologyId.value || !editTechnologyName.value.trim() || !editTechnologyDescription.value.trim()) return;

    loading.value = true;
    error.value = null;

    try {
        const updateData: Record<string, unknown> = {
            name: editTechnologyName.value.trim(),
            type: editTechnologyType.value,
            locale: props.locale,
            description: editTechnologyDescription.value.trim(),
        };

        if (editTechnologyIconPictureId.value) {
            updateData.icon_picture_id = editTechnologyIconPictureId.value;
        }

        const response = await axios.put(
            route('dashboard.api.technologies.update', {
                technology: editTechnologyId.value,
            }),
            updateData,
        );

        const index = allTechnologies.value.findIndex((t) => t.id === editTechnologyId.value);
        if (index !== -1) {
            allTechnologies.value[index] = response.data;
        }

        const associatedIndex = associatedTechnologies.value.findIndex((t) => t.id === editTechnologyId.value);
        if (associatedIndex !== -1) {
            associatedTechnologies.value[associatedIndex] = response.data;
        }

        resetEditForm();
        isEditTechnologyDialogOpen.value = false;

        toast.success('Technologie mise à jour avec succès');
    } catch (err) {
        error.value = 'Erreur lors de la mise à jour de la technologie';
        console.error(err);

        let errorMessage = 'Impossible de mettre à jour cette technologie';
        if (axios.isAxiosError(err) && err.response?.data?.errors) {
            const errors = err.response.data.errors;
            const firstError = Object.values(errors)[0] as string[];
            if (firstError && firstError.length > 0) {
                errorMessage = firstError[0];
            }
        }

        toast.error(errorMessage);
    } finally {
        loading.value = false;
    }
};

const deleteTechnology = async () => {
    if (!technologyToDelete.value) return;

    loading.value = true;
    error.value = null;

    try {
        await axios.delete(
            route('dashboard.api.technologies.destroy', {
                technology: technologyToDelete.value.id,
            }),
        );

        allTechnologies.value = allTechnologies.value.filter((t) => t.id !== technologyToDelete.value?.id);

        associatedTechnologies.value = associatedTechnologies.value.filter((t) => t.id !== technologyToDelete.value?.id);

        technologyToDelete.value = null;
        isDeleteDialogOpen.value = false;

        toast.success('Technologie supprimée avec succès');
    } catch (err) {
        error.value = 'Erreur lors de la suppression de la technologie';
        console.error(err);
        toast.error('Impossible de supprimer cette technologie');
    } finally {
        loading.value = false;
    }
};

const checkTechnologyAssociations = async (technologyId: number): Promise<boolean> => {
    try {
        const response = await axios.get(
            route('dashboard.api.technologies.check-associations', {
                technology: technologyId,
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

const getTechnologyDescription = (technology: Technology): string => {
    if (!technology.description_translation_key) return '';

    const translation = technology.description_translation_key.translations.find((t) => t.locale === props.locale);

    return translation?.text ?? '';
};

const getTechnologyTypeLabel = (type: string): string => {
    const typeObj = technologyTypes.find((t) => t.value === type);
    return typeObj ? typeObj.label : type;
};

const openEditForm = (technology: Technology) => {
    editTechnologyId.value = technology.id;
    editTechnologyName.value = technology.name;
    editTechnologyType.value = technology.type;
    editTechnologyIconPictureId.value = technology.icon_picture?.id ?? undefined;
    editTechnologyDescription.value = getTechnologyDescription(technology);
    isEditTechnologyDialogOpen.value = true;
};

const confirmDeleteTechnology = async (technology: Technology) => {
    technologyToDelete.value = technology;

    technologyHasAssociations.value = await checkTechnologyAssociations(technology.id);

    isDeleteDialogOpen.value = true;
};

const resetTechnologyForm = () => {
    newTechnologyName.value = '';
    newTechnologyType.value = 'framework';
    newTechnologyIconPictureId.value = undefined;
    newTechnologyDescription.value = '';
};

const resetEditForm = () => {
    editTechnologyId.value = null;
    editTechnologyName.value = '';
    editTechnologyType.value = '';
    editTechnologyIconPictureId.value = undefined;
    editTechnologyDescription.value = '';
};

onMounted(() => {
    void fetchAllTechnologies();
    if (props.creationDraftId) {
        void fetchAssociatedTechnologies();
    }
});

watch(
    () => props.creationDraftId,
    (newVal) => {
        if (newVal) {
            void fetchAssociatedTechnologies();
        } else {
            associatedTechnologies.value = [];
        }
    },
);
</script>

<template>
    <div class="space-y-6">
        <HeadingSmall title="Technologies" description="Gérez les technologies utilisées dans cette création." />

        <div v-if="error" class="bg-destructive/10 text-destructive mb-4 rounded-md p-4 text-sm">
            {{ error }}
        </div>

        <div v-if="!props.creationDraftId" class="bg-muted text-muted-foreground rounded-md p-4 text-sm">
            Veuillez d'abord enregistrer le brouillon pour pouvoir ajouter des technologies.
        </div>

        <div v-else>
            <div class="mb-4">
                <div class="mb-2 flex justify-between">
                    <h3 class="text-sm font-medium">Technologies associées</h3>
                    <Button variant="outline" size="sm" @click="modalOpen = true">
                        <Plus class="mr-2 h-4 w-4" />
                        Ajouter
                    </Button>
                </div>

                <div v-if="associatedTechnologies.length === 0" class="bg-muted/30 rounded-md py-8 text-center">
                    <Code class="text-muted-foreground mx-auto h-12 w-12" />
                    <p class="text-muted-foreground mt-2 text-sm">Aucune technologie associée</p>
                    <Button variant="outline" class="mt-4" @click="modalOpen = true"> Ajouter des technologies </Button>
                </div>

                <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <div
                        v-for="technology in associatedTechnologies"
                        :key="technology.id"
                        class="border-border flex items-center rounded-md border p-3"
                    >
                        <div class="mr-3 flex h-8 w-8 flex-shrink-0 items-center justify-center">
                            <img
                                v-if="technology.icon_picture"
                                :src="`/storage/${technology.icon_picture.path_original}`"
                                :alt="technology.name"
                                class="h-full w-full object-contain"
                            />
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ technology.name }}</p>
                            <p class="text-muted-foreground text-xs">{{ getTechnologyTypeLabel(technology.type) }}</p>
                        </div>

                        <Button variant="ghost" size="icon" title="Dissocier" @click="dissociateTechnology(technology.id)">
                            <Minus class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>

            <Dialog v-model:open="modalOpen" class="sm:max-w-[600px]">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Gérer les technologies</DialogTitle>
                    </DialogHeader>

                    <div class="py-4">
                        <div class="relative mb-4">
                            <Search class="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                            <Input v-model="searchQuery" placeholder="Rechercher une technologie..." data-form-type="other" class="pl-8" />
                        </div>

                        <Button variant="outline" class="mb-4 flex w-full items-center justify-center py-2" @click="isAddTechnologyDialogOpen = true">
                            <Plus class="mr-2 h-4 w-4" />
                            Créer une nouvelle technologie
                        </Button>

                        <ScrollArea class="h-[300px]">
                            <div v-if="loading" class="flex h-[200px] items-center justify-center">
                                <Loader2 class="text-primary h-8 w-8 animate-spin" />
                            </div>

                            <div v-else-if="filteredTechnologies.length === 0" class="py-8 text-center">
                                <p class="text-muted-foreground text-sm">Aucun résultat trouvé</p>
                            </div>

                            <div v-else class="space-y-2">
                                <div
                                    v-for="technology in filteredTechnologies"
                                    :key="technology.id"
                                    class="hover:bg-muted flex items-center rounded-md p-3"
                                >
                                    <div class="mr-3 flex h-8 w-8 flex-shrink-0 items-center justify-center">
                                        <img
                                            v-if="technology.icon_picture"
                                            :src="`/storage/${technology.icon_picture.path_original}`"
                                            :alt="technology.name"
                                            class="h-full w-full object-contain"
                                        />
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium">{{ technology.name }}</p>
                                        <p class="text-muted-foreground text-xs">{{ getTechnologyTypeLabel(technology.type) }}</p>
                                    </div>

                                    <div class="flex space-x-1">
                                        <Button
                                            v-if="isTechnologyAssociated(technology.id)"
                                            variant="outline"
                                            size="sm"
                                            title="Dissocier"
                                            @click="dissociateTechnology(technology.id)"
                                        >
                                            Dissocier
                                        </Button>
                                        <Button v-else variant="outline" size="sm" title="Ajouter" @click="associateTechnology(technology.id)">
                                            Ajouter
                                        </Button>

                                        <Button variant="ghost" size="icon" title="Modifier" @click="openEditForm(technology)">
                                            <Pencil class="h-4 w-4" />
                                        </Button>

                                        <Button variant="ghost" size="icon" title="Supprimer" @click="confirmDeleteTechnology(technology)">
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

            <Dialog v-model:open="isAddTechnologyDialogOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Créer une nouvelle technologie</DialogTitle>
                    </DialogHeader>

                    <div class="space-y-4 py-4">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Nom</label>
                            <Input v-model="newTechnologyName" placeholder="Nom de la technologie" data-form-type="other" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium">Type</label>
                            <Select v-model="newTechnologyType">
                                <SelectTrigger>
                                    <SelectValue placeholder="Sélectionner un type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="type in technologyTypes" :key="type.value" :value="type.value">
                                        {{ type.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium">Icône</label>
                            <PictureInput v-model="newTechnologyIconPictureId" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium">Description ({{ props.locale }})</label>
                            <Textarea v-model="newTechnologyDescription" placeholder="Description de la technologie" rows="3" />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" :disabled="loading" @click="isAddTechnologyDialogOpen = false">Annuler</Button>
                        <Button
                            :disabled="!newTechnologyName || !newTechnologyIconPictureId || !newTechnologyDescription || loading"
                            @click="createTechnology"
                        >
                            <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                            Créer
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="isEditTechnologyDialogOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Modifier une technologie</DialogTitle>
                    </DialogHeader>

                    <div class="space-y-4 py-4">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Nom</label>
                            <Input v-model="editTechnologyName" placeholder="Nom de la technologie" data-form-type="other" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium">Type</label>
                            <Select v-model="editTechnologyType">
                                <SelectTrigger>
                                    <SelectValue placeholder="Sélectionner un type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="type in technologyTypes" :key="type.value" :value="type.value">
                                        {{ type.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium">Icône</label>
                            <PictureInput v-model="editTechnologyIconPictureId" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium">Description ({{ props.locale }})</label>
                            <Textarea v-model="editTechnologyDescription" placeholder="Description de la technologie" rows="3" />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" :disabled="loading" @click="isEditTechnologyDialogOpen = false">Annuler</Button>
                        <Button :disabled="!editTechnologyName || !editTechnologyDescription || loading" @click="updateTechnology">
                            <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                            Enregistrer
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <AlertDialog v-model:open="isDeleteDialogOpen">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Êtes-vous sûr de vouloir supprimer cette technologie ?</AlertDialogTitle>
                        <AlertDialogDescription>
                            <span v-if="technologyHasAssociations" class="text-destructive font-medium">
                                Attention : cette technologie est associée à {{ associationsDetails.creations_count }} création(s) et
                                {{ associationsDetails.creation_drafts_count }} brouillon(s).
                            </span>
                            <span v-else> Cette action est irréversible. La technologie sera supprimée définitivement de la base de données. </span>
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Annuler</AlertDialogCancel>
                        <AlertDialogAction class="bg-destructive text-destructive-foreground hover:bg-destructive/90" @click="deleteTechnology">
                            <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                            Supprimer
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    </div>
</template>
