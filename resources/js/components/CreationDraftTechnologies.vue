<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/components/ui/toast';
import axios from 'axios';
import { Code, Loader2, Minus, Pencil, Plus, Search, Star, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface Translation {
    id: number;
    translation_key_id: number;
    locale: string;
    text: string;
}

interface TranslationKey {
    id: number;
    key: string;
    translations: Translation[];
}

interface Technology {
    id: number;
    name: string;
    type: 'framework' | 'library' | 'language' | 'other';
    featured: boolean;
    svg_icon: string;
    description_translation_key_id: number;
    created_at: string;
    updated_at: string;
    description_translation_key?: TranslationKey;
}

const props = defineProps<{
    creationDraftId: number | null;
    locale: string;
}>();

const { toast } = useToast();

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
const newTechnologySvg = ref('');
const newTechnologyFeatured = ref(false);
const newTechnologyDescription = ref('');
const editTechnologyId = ref<number | null>(null);
const editTechnologyName = ref('');
const editTechnologyType = ref('');
const editTechnologySvg = ref('');
const editTechnologyFeatured = ref(false);
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
        toast({
            title: 'Succès',
            description: 'Technologie associée avec succès',
        });
    } catch (err) {
        error.value = "Erreur lors de l'association de la technologie";
        console.error(err);
        toast({
            title: 'Erreur',
            description: "Impossible d'associer cette technologie",
            variant: 'destructive',
        });
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
        toast({
            title: 'Succès',
            description: 'Technologie dissociée avec succès',
        });
    } catch (err) {
        error.value = 'Erreur lors de la dissociation de la technologie';
        console.error(err);
        toast({
            title: 'Erreur',
            description: 'Impossible de dissocier cette technologie',
            variant: 'destructive',
        });
    } finally {
        loading.value = false;
    }
};

const createTechnology = async () => {
    if (!newTechnologyName.value.trim() || !newTechnologySvg.value.trim() || !newTechnologyDescription.value.trim()) return;

    loading.value = true;
    error.value = null;

    try {
        const response = await axios.post(route('dashboard.api.technologies.store'), {
            name: newTechnologyName.value.trim(),
            type: newTechnologyType.value,
            svg_icon: newTechnologySvg.value.trim(),
            featured: newTechnologyFeatured.value,
            locale: props.locale,
            description: newTechnologyDescription.value.trim(),
        });

        allTechnologies.value.push(response.data);
        resetTechnologyForm();
        isAddTechnologyDialogOpen.value = false;

        toast({
            title: 'Succès',
            description: 'Technologie créée avec succès',
        });

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

        toast({
            title: 'Erreur',
            description: errorMessage,
            variant: 'destructive',
        });
    } finally {
        loading.value = false;
    }
};

const updateTechnology = async () => {
    if (!editTechnologyId.value || !editTechnologyName.value.trim() || !editTechnologySvg.value.trim() || !editTechnologyDescription.value.trim())
        return;

    loading.value = true;
    error.value = null;

    try {
        const response = await axios.put(
            route('dashboard.api.technologies.update', {
                technology: editTechnologyId.value,
            }),
            {
                name: editTechnologyName.value.trim(),
                type: editTechnologyType.value,
                svg_icon: editTechnologySvg.value.trim(),
                featured: editTechnologyFeatured.value,
                locale: props.locale,
                description: editTechnologyDescription.value.trim(),
            },
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

        toast({
            title: 'Succès',
            description: 'Technologie mise à jour avec succès',
        });
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

        toast({
            title: 'Erreur',
            description: errorMessage,
            variant: 'destructive',
        });
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

        toast({
            title: 'Succès',
            description: 'Technologie supprimée avec succès',
        });
    } catch (err) {
        error.value = 'Erreur lors de la suppression de la technologie';
        console.error(err);
        toast({
            title: 'Erreur',
            description: 'Impossible de supprimer cette technologie',
            variant: 'destructive',
        });
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

    return translation?.text || '';
};

const getTechnologyTypeLabel = (type: string): string => {
    const typeObj = technologyTypes.find((t) => t.value === type);
    return typeObj ? typeObj.label : type;
};

const openEditForm = (technology: Technology) => {
    editTechnologyId.value = technology.id;
    editTechnologyName.value = technology.name;
    editTechnologyType.value = technology.type;
    editTechnologySvg.value = technology.svg_icon;
    editTechnologyFeatured.value = technology.featured;
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
    newTechnologySvg.value = '';
    newTechnologyFeatured.value = false;
    newTechnologyDescription.value = '';
};

const resetEditForm = () => {
    editTechnologyId.value = null;
    editTechnologyName.value = '';
    editTechnologyType.value = '';
    editTechnologySvg.value = '';
    editTechnologyFeatured.value = false;
    editTechnologyDescription.value = '';
};

onMounted(() => {
    fetchAllTechnologies();
    if (props.creationDraftId) {
        fetchAssociatedTechnologies();
    }
});

watch(
    () => props.creationDraftId,
    (newVal) => {
        if (newVal) {
            fetchAssociatedTechnologies();
        } else {
            associatedTechnologies.value = [];
        }
    },
);
</script>

<template>
    <div class="space-y-6">
        <HeadingSmall title="Technologies" description="Gérez les technologies utilisées dans cette création." />

        <div v-if="error" class="mb-4 rounded-md bg-destructive/10 p-4 text-sm text-destructive">
            {{ error }}
        </div>

        <div v-if="!props.creationDraftId" class="rounded-md bg-muted p-4 text-sm text-muted-foreground">
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

                <div v-if="associatedTechnologies.length === 0" class="rounded-md bg-muted/30 py-8 text-center">
                    <Code class="mx-auto h-12 w-12 text-muted-foreground" />
                    <p class="mt-2 text-sm text-muted-foreground">Aucune technologie associée</p>
                    <Button variant="outline" class="mt-4" @click="modalOpen = true"> Ajouter des technologies </Button>
                </div>

                <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <div
                        v-for="technology in associatedTechnologies"
                        :key="technology.id"
                        class="flex items-center rounded-md border border-border p-3"
                    >
                        <div class="mr-3 h-8 w-8 flex-shrink-0" v-html="technology.svg_icon"></div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center">
                                <p class="truncate text-sm font-medium">{{ technology.name }}</p>
                                <Star v-if="technology.featured" class="ml-1 h-3 w-3 text-amber-500" />
                            </div>
                            <p class="text-xs text-muted-foreground">{{ getTechnologyTypeLabel(technology.type) }}</p>
                        </div>

                        <Button variant="ghost" size="icon" @click="dissociateTechnology(technology.id)" title="Dissocier">
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
                            <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input v-model="searchQuery" placeholder="Rechercher une technologie..." class="pl-8" />
                        </div>

                        <Button variant="outline" class="mb-4 flex w-full items-center justify-center py-2" @click="isAddTechnologyDialogOpen = true">
                            <Plus class="mr-2 h-4 w-4" />
                            Créer une nouvelle technologie
                        </Button>

                        <ScrollArea class="h-[300px]">
                            <div v-if="loading" class="flex h-[200px] items-center justify-center">
                                <Loader2 class="h-8 w-8 animate-spin text-primary" />
                            </div>

                            <div v-else-if="filteredTechnologies.length === 0" class="py-8 text-center">
                                <p class="text-sm text-muted-foreground">Aucun résultat trouvé</p>
                            </div>

                            <div v-else class="space-y-2">
                                <div
                                    v-for="technology in filteredTechnologies"
                                    :key="technology.id"
                                    class="flex items-center rounded-md p-3 hover:bg-muted"
                                >
                                    <div class="mr-3 h-8 w-8 flex-shrink-0" v-html="technology.svg_icon"></div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center">
                                            <p class="truncate text-sm font-medium">{{ technology.name }}</p>
                                            <Star v-if="technology.featured" class="ml-1 h-3 w-3 text-amber-500" />
                                        </div>
                                        <p class="text-xs text-muted-foreground">{{ getTechnologyTypeLabel(technology.type) }}</p>
                                    </div>

                                    <div class="flex space-x-1">
                                        <Button
                                            v-if="isTechnologyAssociated(technology.id)"
                                            variant="outline"
                                            size="sm"
                                            @click="dissociateTechnology(technology.id)"
                                            title="Dissocier"
                                        >
                                            Dissocier
                                        </Button>
                                        <Button v-else variant="outline" size="sm" @click="associateTechnology(technology.id)" title="Ajouter">
                                            Ajouter
                                        </Button>

                                        <Button variant="ghost" size="icon" @click="openEditForm(technology)" title="Modifier">
                                            <Pencil class="h-4 w-4" />
                                        </Button>

                                        <Button variant="ghost" size="icon" @click="confirmDeleteTechnology(technology)" title="Supprimer">
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
                            <Input v-model="newTechnologyName" placeholder="Nom de la technologie" />
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
                            <label class="text-sm font-medium">Icône SVG</label>
                            <Textarea v-model="newTechnologySvg" placeholder="<svg>...</svg>" rows="3" />
                            <div v-if="newTechnologySvg" class="mt-2 flex justify-center rounded-md border p-2">
                                <div class="h-10 w-10" v-html="newTechnologySvg"></div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium">Description ({{ props.locale }})</label>
                            <Textarea v-model="newTechnologyDescription" placeholder="Description de la technologie" rows="3" />
                        </div>

                        <div class="flex items-center space-x-2">
                            <Switch v-model="newTechnologyFeatured" id="new-tech-featured" />
                            <label for="new-tech-featured" class="text-sm font-medium">Mettre en avant cette technologie</label>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" @click="isAddTechnologyDialogOpen = false" :disabled="loading">Annuler</Button>
                        <Button :disabled="!newTechnologyName || !newTechnologySvg || !newTechnologyDescription || loading" @click="createTechnology">
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
                            <Input v-model="editTechnologyName" placeholder="Nom de la technologie" />
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
                            <label class="text-sm font-medium">Icône SVG</label>
                            <Textarea v-model="editTechnologySvg" placeholder="<svg>...</svg>" rows="3" />
                            <div v-if="editTechnologySvg" class="mt-2 flex justify-center rounded-md border p-2">
                                <div class="h-10 w-10" v-html="editTechnologySvg"></div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium">Description ({{ props.locale }})</label>
                            <Textarea v-model="editTechnologyDescription" placeholder="Description de la technologie" rows="3" />
                        </div>

                        <div class="flex items-center space-x-2">
                            <Switch v-model="editTechnologyFeatured" id="edit-tech-featured" />
                            <label for="edit-tech-featured" class="text-sm font-medium">Mettre en avant cette technologie</label>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" @click="isEditTechnologyDialogOpen = false" :disabled="loading">Annuler</Button>
                        <Button
                            :disabled="!editTechnologyName || !editTechnologySvg || !editTechnologyDescription || loading"
                            @click="updateTechnology"
                        >
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
                            <span v-if="technologyHasAssociations" class="font-medium text-destructive">
                                Attention : cette technologie est associée à {{ associationsDetails.creations_count }} création(s) et
                                {{ associationsDetails.creation_drafts_count }} brouillon(s).
                            </span>
                            <span v-else> Cette action est irréversible. La technologie sera supprimée définitivement de la base de données. </span>
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Annuler</AlertDialogCancel>
                        <AlertDialogAction @click="deleteTechnology" class="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                            <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                            Supprimer
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    </div>
</template>
