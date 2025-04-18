<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import PictureInput from '@/components/PictureInput.vue';
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
import { useToast } from '@/components/ui/toast';
import { Person } from '@/types';
import axios from 'axios';
import { Loader2, Pencil, Plus, Search, Trash2, User, UserMinus, UserPlus } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    creationDraftId: number | null;
}>();

const { toast } = useToast();

const loading = ref(false);
const error = ref<string | null>(null);
const searchQuery = ref('');
const modalOpen = ref(false);
const allPeople = ref<Person[]>([]);
const associatedPeople = ref<Person[]>([]);

const isAddPersonDialogOpen = ref(false);
const isEditPersonDialogOpen = ref(false);
const isDeleteDialogOpen = ref(false);
const newPersonName = ref('');
const newPersonPictureId = ref<number | undefined>(undefined);
const editPersonId = ref<number | null>(null);
const editPersonName = ref('');
const editPersonPictureId = ref<number | undefined>(undefined);
const personToDelete = ref<Person | null>(null);
const personHasAssociations = ref(false);
const associationsDetails = ref<{ creations_count: number; creation_drafts_count: number }>({
    creations_count: 0,
    creation_drafts_count: 0,
});

const filteredPeople = computed(() => {
    if (!searchQuery.value.trim()) return allPeople.value;

    const query = searchQuery.value.toLowerCase();
    return allPeople.value.filter((person) => person.name.toLowerCase().includes(query));
});

const fetchAllPeople = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get(route('dashboard.api.people.index'));
        allPeople.value = response.data;
    } catch (err) {
        error.value = 'Erreur lors du chargement des personnes';
        console.error(err);
    } finally {
        loading.value = false;
    }
};

const fetchAssociatedPeople = async () => {
    if (!props.creationDraftId) return;

    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get(
            route('dashboard.api.creation-drafts.people', {
                creation_draft: props.creationDraftId,
            }),
        );

        associatedPeople.value = response.data;
    } catch (err) {
        error.value = 'Erreur lors du chargement des personnes associées';
        console.error(err);
    } finally {
        loading.value = false;
    }
};

const isPersonAssociated = (personId: number): boolean => {
    return associatedPeople.value.some((p) => p.id === personId);
};

const associatePerson = async (personId: number) => {
    if (!props.creationDraftId) return;

    loading.value = true;
    error.value = null;

    try {
        await axios.post(
            route('dashboard.api.creation-drafts.attach-person', {
                creation_draft: props.creationDraftId,
            }),
            {
                person_id: personId,
            },
        );

        await fetchAssociatedPeople();
        toast({
            title: 'Succès',
            description: 'Personne associée avec succès',
        });
    } catch (err) {
        error.value = "Erreur lors de l'association de la personne";
        console.error(err);
        toast({
            title: 'Erreur',
            description: "Impossible d'associer cette personne",
            variant: 'destructive',
        });
    } finally {
        loading.value = false;
    }
};

const dissociatePerson = async (personId: number) => {
    if (!props.creationDraftId) return;

    loading.value = true;
    error.value = null;

    try {
        await axios.post(
            route('dashboard.api.creation-drafts.detach-person', {
                creation_draft: props.creationDraftId,
            }),
            {
                person_id: personId,
            },
        );

        associatedPeople.value = associatedPeople.value.filter((p) => p.id !== personId);
        toast({
            title: 'Succès',
            description: 'Personne dissociée avec succès',
        });
    } catch (err) {
        error.value = 'Erreur lors de la dissociation de la personne';
        console.error(err);
        toast({
            title: 'Erreur',
            description: 'Impossible de dissocier cette personne',
            variant: 'destructive',
        });
    } finally {
        loading.value = false;
    }
};

const createPerson = async () => {
    if (!newPersonName.value.trim()) return;

    loading.value = true;
    error.value = null;

    try {
        const response = await axios.post(route('dashboard.api.people.store'), {
            name: newPersonName.value.trim(),
            picture_id: newPersonPictureId.value,
        });

        allPeople.value.push(response.data);
        resetPersonForm();
        isAddPersonDialogOpen.value = false;

        toast({
            title: 'Succès',
            description: 'Personne créée avec succès',
        });

        if (props.creationDraftId) {
            await associatePerson(response.data.id);
        }
    } catch (err) {
        error.value = 'Erreur lors de la création de la personne';
        console.error(err);
        toast({
            title: 'Erreur',
            description: 'Impossible de créer cette personne',
            variant: 'destructive',
        });
    } finally {
        loading.value = false;
    }
};

const updatePerson = async () => {
    if (!editPersonId.value || !editPersonName.value.trim()) return;

    loading.value = true;
    error.value = null;

    try {
        const response = await axios.put(
            route('dashboard.api.people.update', {
                person: editPersonId.value,
            }),
            {
                name: editPersonName.value.trim(),
                picture_id: editPersonPictureId.value,
            },
        );

        const index = allPeople.value.findIndex((p) => p.id === editPersonId.value);
        if (index !== -1) {
            allPeople.value[index] = response.data;
        }

        const associatedIndex = associatedPeople.value.findIndex((p) => p.id === editPersonId.value);
        if (associatedIndex !== -1) {
            associatedPeople.value[associatedIndex] = response.data;
        }

        resetEditForm();
        isEditPersonDialogOpen.value = false;

        toast({
            title: 'Succès',
            description: 'Personne mise à jour avec succès',
        });
    } catch (err) {
        error.value = 'Erreur lors de la mise à jour de la personne';
        console.error(err);
        toast({
            title: 'Erreur',
            description: 'Impossible de mettre à jour cette personne',
            variant: 'destructive',
        });
    } finally {
        loading.value = false;
    }
};

const deletePerson = async () => {
    if (!personToDelete.value) return;

    loading.value = true;
    error.value = null;

    try {
        await axios.delete(
            route('dashboard.api.people.destroy', {
                person: personToDelete.value.id,
            }),
        );

        allPeople.value = allPeople.value.filter((p) => p.id !== personToDelete.value?.id);

        associatedPeople.value = associatedPeople.value.filter((p) => p.id !== personToDelete.value?.id);

        personToDelete.value = null;
        isDeleteDialogOpen.value = false;

        toast({
            title: 'Succès',
            description: 'Personne supprimée avec succès',
        });
    } catch (err) {
        error.value = 'Erreur lors de la suppression de la personne';
        console.error(err);
        toast({
            title: 'Erreur',
            description: 'Impossible de supprimer cette personne',
            variant: 'destructive',
        });
    } finally {
        loading.value = false;
    }
};

const checkPersonAssociations = async (personId: number): Promise<boolean> => {
    try {
        const response = await axios.get(
            route('dashboard.api.people.check-associations', {
                person: personId,
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

const openEditForm = (person: Person) => {
    editPersonId.value = person.id;
    editPersonName.value = person.name;
    editPersonPictureId.value = person.picture_id || undefined;
    isEditPersonDialogOpen.value = true;
};

const confirmDeletePerson = async (person: Person) => {
    personToDelete.value = person;

    personHasAssociations.value = await checkPersonAssociations(person.id);

    isDeleteDialogOpen.value = true;
};

const resetPersonForm = () => {
    newPersonName.value = '';
    newPersonPictureId.value = undefined;
};

const resetEditForm = () => {
    editPersonId.value = null;
    editPersonName.value = '';
    editPersonPictureId.value = undefined;
};

onMounted(() => {
    fetchAllPeople();
    if (props.creationDraftId) {
        fetchAssociatedPeople();
    }
});

watch(
    () => props.creationDraftId,
    (newVal) => {
        if (newVal) {
            fetchAssociatedPeople();
        } else {
            associatedPeople.value = [];
        }
    },
);
</script>

<template>
    <div class="space-y-6">
        <HeadingSmall title="Contributeurs" description="Gérez les personnes ayant contribué à cette création." />

        <div v-if="error" class="mb-4 rounded-md bg-destructive/10 p-4 text-sm text-destructive">
            {{ error }}
        </div>

        <div v-if="!props.creationDraftId" class="rounded-md bg-muted p-4 text-sm text-muted-foreground">
            Veuillez d'abord enregistrer le brouillon pour pouvoir ajouter des contributeurs.
        </div>

        <div v-else>
            <div class="mb-4">
                <div class="mb-2 flex justify-between">
                    <h3 class="text-sm font-medium">Personnes associées</h3>
                    <Button variant="outline" size="sm" @click="modalOpen = true">
                        <UserPlus class="mr-2 h-4 w-4" />
                        Ajouter
                    </Button>
                </div>

                <div v-if="associatedPeople.length === 0" class="rounded-md bg-muted/30 py-8 text-center">
                    <User class="mx-auto h-12 w-12 text-muted-foreground" />
                    <p class="mt-2 text-sm text-muted-foreground">Aucun contributeur associé</p>
                    <Button variant="outline" class="mt-4" @click="modalOpen = true"> Ajouter des contributeurs </Button>
                </div>

                <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div v-for="person in associatedPeople" :key="person.id" class="flex items-center rounded-md border border-border p-3">
                        <div class="h-10 w-10 flex-shrink-0 overflow-hidden rounded-full bg-muted">
                            <img
                                v-if="person.picture?.path_original"
                                :src="`/storage/${person.picture.path_original}`"
                                :alt="person.name"
                                class="h-full w-full object-cover"
                            />
                            <User v-else class="h-full w-full p-2 text-muted-foreground" />
                        </div>

                        <div class="ml-3 min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ person.name }}</p>
                        </div>

                        <div class="ml-2 flex space-x-1">
                            <Button variant="ghost" size="icon" @click="openEditForm(person)" title="Modifier">
                                <Pencil class="h-4 w-4" />
                            </Button>
                            <Button variant="ghost" size="icon" @click="dissociatePerson(person.id)" title="Dissocier">
                                <UserMinus class="h-4 w-4" />
                            </Button>
                            <Button variant="ghost" size="icon" @click="confirmDeletePerson(person)" title="Supprimer">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <Dialog v-model:open="modalOpen" class="sm:max-w-[600px]">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Ajouter des contributeurs</DialogTitle>
                    </DialogHeader>

                    <div class="py-4">
                        <div class="relative mb-4">
                            <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input v-model="searchQuery" placeholder="Rechercher une personne..." class="pl-8" />
                        </div>

                        <Button variant="outline" class="mb-4 flex w-full items-center justify-center py-6" @click="isAddPersonDialogOpen = true">
                            <Plus class="mr-2 h-4 w-4" />
                            Créer un nouveau contributeur
                        </Button>

                        <ScrollArea class="h-[300px]">
                            <div v-if="loading" class="flex h-[200px] items-center justify-center">
                                <Loader2 class="h-8 w-8 animate-spin text-primary" />
                            </div>

                            <div v-else-if="filteredPeople.length === 0" class="py-8 text-center">
                                <p class="text-sm text-muted-foreground">Aucun résultat trouvé</p>
                            </div>

                            <div v-else class="space-y-2">
                                <div v-for="person in filteredPeople" :key="person.id" class="flex items-center rounded-md border border-border p-3">
                                    <div class="h-10 w-10 flex-shrink-0 overflow-hidden rounded-full bg-muted">
                                        <img
                                            v-if="person.picture?.path_original"
                                            :src="`/storage/${person.picture.path_original}`"
                                            :alt="person.name"
                                            class="h-full w-full object-cover"
                                        />
                                        <User v-else class="h-full w-full p-2 text-muted-foreground" />
                                    </div>

                                    <div class="ml-3 min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium">{{ person.name }}</p>
                                    </div>

                                    <div class="ml-2 flex space-x-1">
                                        <Button variant="ghost" size="icon" @click="openEditForm(person)" title="Modifier">
                                            <Pencil class="h-4 w-4" />
                                        </Button>

                                        <Button
                                            v-if="isPersonAssociated(person.id)"
                                            variant="outline"
                                            size="sm"
                                            @click="dissociatePerson(person.id)"
                                            title="Dissocier"
                                        >
                                            Dissocier
                                        </Button>
                                        <Button v-else variant="outline" size="sm" @click="associatePerson(person.id)" title="Ajouter">
                                            Ajouter
                                        </Button>

                                        <Button variant="ghost" size="icon" @click="confirmDeletePerson(person)" title="Supprimer">
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

            <Dialog v-model:open="isAddPersonDialogOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Créer un nouveau contributeur</DialogTitle>
                    </DialogHeader>

                    <div class="space-y-4 py-4">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Nom</label>
                            <Input v-model="newPersonName" placeholder="Nom du contributeur" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium">Photo (optionnelle)</label>
                            <PictureInput v-model="newPersonPictureId" />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" @click="isAddPersonDialogOpen = false" :disabled="loading">Annuler</Button>
                        <Button :disabled="!newPersonName || loading" @click="createPerson">
                            <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                            Créer
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="isEditPersonDialogOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Modifier un contributeur</DialogTitle>
                    </DialogHeader>

                    <div class="space-y-4 py-4">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Nom</label>
                            <Input v-model="editPersonName" placeholder="Nom du contributeur" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium">Photo (optionnelle)</label>
                            <PictureInput v-model="editPersonPictureId" />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" @click="isEditPersonDialogOpen = false" :disabled="loading">Annuler</Button>
                        <Button :disabled="!editPersonName || loading" @click="updatePerson">
                            <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                            Enregistrer
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <AlertDialog v-model:open="isDeleteDialogOpen">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Êtes-vous sûr de vouloir supprimer cette personne ?</AlertDialogTitle>
                        <AlertDialogDescription>
                            <span v-if="personHasAssociations" class="font-medium text-destructive">
                                Attention : cette personne est associée à {{ associationsDetails.creations_count }} création(s) et
                                {{ associationsDetails.creation_drafts_count }} brouillon(s).
                            </span>
                            <span v-else> Cette action est irréversible. La personne sera supprimée définitivement de la base de données. </span>
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Annuler</AlertDialogCancel>
                        <AlertDialogAction @click="deletePerson" class="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                            <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                            Supprimer
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    </div>
</template>
