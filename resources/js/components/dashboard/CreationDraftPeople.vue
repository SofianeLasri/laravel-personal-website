<script setup lang="ts">
import HeadingSmall from '@/components/dashboard/HeadingSmall.vue';
import PictureInput from '@/components/dashboard/PictureInput.vue';
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
import { Person } from '@/types';
import axios from 'axios';
import { Link, Loader2, Pencil, Plus, Search, Trash2, User, UserMinus, UserPlus } from 'lucide-vue-next';
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
const allPeople = ref<Person[]>([]);
const associatedPeople = ref<Person[]>([]);

const isAddPersonDialogOpen = ref(false);
const isEditPersonDialogOpen = ref(false);
const isDeleteDialogOpen = ref(false);
const newPersonName = ref('');
const newPersonUrl = ref('');
const newPersonPictureId = ref<number | undefined>(undefined);
const editPersonId = ref<number | null>(null);
const editPersonName = ref('');
const editPersonUrl = ref('');
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
        toast.success('Personne associée avec succès');
    } catch (err) {
        error.value = "Erreur lors de l'association de la personne";
        console.error(err);
        toast.error("Impossible d'associer cette personne");
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
        toast.success('Personne dissociée avec succès');
    } catch (err) {
        error.value = 'Erreur lors de la dissociation de la personne';
        console.error(err);
        toast.error('Impossible de dissocier cette personne');
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
            url: newPersonUrl.value.trim() || null,
            picture_id: newPersonPictureId.value,
        });

        allPeople.value.push(response.data);
        resetPersonForm();
        isAddPersonDialogOpen.value = false;

        toast.success('Personne créée avec succès');

        if (props.creationDraftId) {
            await associatePerson(response.data.id);
        }
    } catch (err) {
        error.value = 'Erreur lors de la création de la personne';
        console.error(err);
        toast.error('Impossible de créer cette personne');
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
                url: editPersonUrl.value.trim() || null,
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

        toast.success('Personne mise à jour avec succès');
    } catch (err) {
        error.value = 'Erreur lors de la mise à jour de la personne';
        console.error(err);
        toast.error('Impossible de mettre à jour cette personne');
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

        toast.success('Personne supprimée avec succès');
    } catch (err) {
        error.value = 'Erreur lors de la suppression de la personne';
        console.error(err);
        toast.error('Impossible de supprimer cette personne');
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
    editPersonUrl.value = person.url || '';
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
    newPersonUrl.value = '';
    newPersonPictureId.value = undefined;
};

const resetEditForm = () => {
    editPersonId.value = null;
    editPersonName.value = '';
    editPersonUrl.value = '';
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

        <div v-if="error" class="bg-destructive/10 text-destructive mb-4 rounded-md p-4 text-sm">
            {{ error }}
        </div>

        <div v-if="!props.creationDraftId" class="bg-muted text-muted-foreground rounded-md p-4 text-sm">
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

                <div v-if="associatedPeople.length === 0" class="bg-muted/30 rounded-md py-8 text-center">
                    <User class="text-muted-foreground mx-auto h-12 w-12" />
                    <p class="text-muted-foreground mt-2 text-sm">Aucun contributeur associé</p>
                    <Button variant="outline" class="mt-4" @click="modalOpen = true"> Ajouter des contributeurs </Button>
                </div>

                <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div v-for="person in associatedPeople" :key="person.id" class="border-border flex items-center rounded-md border p-3">
                        <div class="bg-muted h-10 w-10 flex-shrink-0 overflow-hidden rounded-full">
                            <img
                                v-if="person.picture?.path_original"
                                :src="`/storage/${person.picture.path_original}`"
                                :alt="person.name"
                                class="h-full w-full object-cover"
                            />
                            <User v-else class="text-muted-foreground h-full w-full p-2" />
                        </div>

                        <div class="ml-3 min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ person.name }}</p>
                            <a
                                v-if="person.url"
                                :href="person.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-muted-foreground flex items-center text-xs hover:underline"
                            >
                                <Link class="mr-1 h-3 w-3" />
                                {{ person.url }}
                            </a>
                        </div>

                        <div class="ml-2 flex space-x-1">
                            <Button variant="ghost" size="icon" title="Modifier" @click="openEditForm(person)">
                                <Pencil class="h-4 w-4" />
                            </Button>
                            <Button variant="ghost" size="icon" title="Dissocier" @click="dissociatePerson(person.id)">
                                <UserMinus class="h-4 w-4" />
                            </Button>
                            <Button variant="ghost" size="icon" title="Supprimer" @click="confirmDeletePerson(person)">
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
                            <Search class="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                            <Input v-model="searchQuery" placeholder="Rechercher une personne..." class="pl-8" data-form-type="other" />
                        </div>

                        <Button variant="outline" class="mb-4 flex w-full items-center justify-center py-6" @click="isAddPersonDialogOpen = true">
                            <Plus class="mr-2 h-4 w-4" />
                            Créer un nouveau contributeur
                        </Button>

                        <ScrollArea class="h-[300px]">
                            <div v-if="loading" class="flex h-[200px] items-center justify-center">
                                <Loader2 class="text-primary h-8 w-8 animate-spin" />
                            </div>

                            <div v-else-if="filteredPeople.length === 0" class="py-8 text-center">
                                <p class="text-muted-foreground text-sm">Aucun résultat trouvé</p>
                            </div>

                            <div v-else class="space-y-2">
                                <div v-for="person in filteredPeople" :key="person.id" class="border-border flex items-center rounded-md border p-3">
                                    <div class="bg-muted h-10 w-10 flex-shrink-0 overflow-hidden rounded-full">
                                        <img
                                            v-if="person.picture?.path_original"
                                            :src="`/storage/${person.picture.path_original}`"
                                            :alt="person.name"
                                            class="h-full w-full object-cover"
                                        />
                                        <User v-else class="text-muted-foreground h-full w-full p-2" />
                                    </div>

                                    <div class="ml-3 min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium">{{ person.name }}</p>
                                        <a
                                            v-if="person.url"
                                            :href="person.url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="text-muted-foreground flex items-center text-xs hover:underline"
                                        >
                                            <Link class="mr-1 h-3 w-3" />
                                            {{ person.url }}
                                        </a>
                                    </div>

                                    <div class="ml-2 flex space-x-1">
                                        <Button variant="ghost" size="icon" title="Modifier" @click="openEditForm(person)">
                                            <Pencil class="h-4 w-4" />
                                        </Button>

                                        <Button
                                            v-if="isPersonAssociated(person.id)"
                                            variant="outline"
                                            size="sm"
                                            title="Dissocier"
                                            @click="dissociatePerson(person.id)"
                                        >
                                            Dissocier
                                        </Button>
                                        <Button v-else variant="outline" size="sm" title="Ajouter" @click="associatePerson(person.id)">
                                            Ajouter
                                        </Button>

                                        <Button variant="ghost" size="icon" title="Supprimer" @click="confirmDeletePerson(person)">
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
                            <Input v-model="newPersonName" placeholder="Nom du contributeur" data-form-type="other" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium">URL (optionnelle)</label>
                            <Input v-model="newPersonUrl" placeholder="https://example.com" data-form-type="other" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium">Photo (optionnelle)</label>
                            <PictureInput v-model="newPersonPictureId" />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" :disabled="loading" @click="isAddPersonDialogOpen = false">Annuler</Button>
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
                            <Input v-model="editPersonName" placeholder="Nom du contributeur" data-form-type="other" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium">URL (optionnelle)</label>
                            <Input v-model="editPersonUrl" placeholder="https://example.com" data-form-type="other" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium">Photo (optionnelle)</label>
                            <PictureInput v-model="editPersonPictureId" />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" :disabled="loading" @click="isEditPersonDialogOpen = false">Annuler</Button>
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
                            <span v-if="personHasAssociations" class="text-destructive font-medium">
                                Attention : cette personne est associée à {{ associationsDetails.creations_count }} création(s) et
                                {{ associationsDetails.creation_drafts_count }} brouillon(s).
                            </span>
                            <span v-else> Cette action est irréversible. La personne sera supprimée définitivement de la base de données. </span>
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Annuler</AlertDialogCancel>
                        <AlertDialogAction class="bg-destructive text-destructive-foreground hover:bg-destructive/90" @click="deletePerson">
                            <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                            Supprimer
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    </div>
</template>
