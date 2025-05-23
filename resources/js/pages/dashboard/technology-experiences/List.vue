<script setup lang="ts">
import Heading from '@/components/dashboard/Heading.vue';
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem, TechnologyExperience, TechnologyWithCreationsCount } from '@/types';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Code, Edit, Loader2, Plus, Search, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    technologies: TechnologyWithCreationsCount[];
    technologyExperiences: TechnologyExperience[];
}>();

// État local
const searchQuery = ref('');
const locale = ref<'fr' | 'en'>('fr');
const isAddTechnologyDialogOpen = ref(false);
const isEditTechnologyDialogOpen = ref(false);
const isAddExperienceDialogOpen = ref(false);
const isEditExperienceDialogOpen = ref(false);
const isDeleteTechnologyDialogOpen = ref(false);
const isDeleteExperienceDialogOpen = ref(false);
const isLoading = ref(false);

// Formes et valeurs pour les technologies
const newTechnologyName = ref('');
const newTechnologyType = ref('framework');
const newTechnologySvg = ref('');
const newTechnologyDescription = ref('');

const editTechnologyId = ref<number | null>(null);
const editTechnologyName = ref('');
const editTechnologyType = ref('');
const editTechnologySvg = ref('');
const editTechnologyDescription = ref('');
const technologyToDelete = ref<TechnologyWithCreationsCount | null>(null);

// Formes et valeurs pour les expériences
const newExperienceTechnologyId = ref<number | null>(null);
const newExperienceDescription = ref('');

const editExperienceId = ref<number | null>(null);
const editExperienceTechnologyId = ref<number | null>(null);
const editExperienceDescription = ref('');
const experienceToDelete = ref<TechnologyExperience | null>(null);

const technologyTypes = [
    { value: 'framework', label: 'Framework' },
    { value: 'library', label: 'Bibliothèque' },
    { value: 'language', label: 'Langage' },
    { value: 'other', label: 'Autre' },
];

// Filtrage des technologies
const filteredTechnologies = computed(() => {
    if (!searchQuery.value.trim()) return props.technologies;

    const query = searchQuery.value.toLowerCase();
    return props.technologies.filter((tech) => tech.name.toLowerCase().includes(query) || tech.type.toLowerCase().includes(query));
});

// Helpers pour obtenir des traductions
const getTechnologyDescription = (technology: TechnologyWithCreationsCount): string => {
    if (!technology.description_translation_key) return '';

    const translation = technology.description_translation_key.translations.find((t) => t.locale === locale.value);

    return translation?.text || '';
};

const getExperienceDescription = (experience: TechnologyExperience): string => {
    if (!experience.description_translation_key) return '';

    const translation = experience.description_translation_key.translations.find((t) => t.locale === locale.value);

    return translation?.text || '';
};

const getTechnologyTypeLabel = (type: string): string => {
    const typeObj = technologyTypes.find((t) => t.value === type);
    return typeObj ? typeObj.label : type;
};

// Méthodes pour les technologies
const createTechnology = async () => {
    if (!newTechnologyName.value.trim() || !newTechnologySvg.value.trim() || !newTechnologyDescription.value.trim()) return;

    isLoading.value = true;

    try {
        await axios.post(route('dashboard.api.technologies.store'), {
            name: newTechnologyName.value.trim(),
            type: newTechnologyType.value,
            svg_icon: newTechnologySvg.value.trim(),
            locale: locale.value,
            description: newTechnologyDescription.value.trim(),
        });

        resetTechnologyForm();
        isAddTechnologyDialogOpen.value = false;
        toast.success('Technologie créée avec succès');

        window.location.reload();
    } catch (err) {
        console.error('Erreur lors de la création de la technologie:', err);

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
        isLoading.value = false;
    }
};

const updateTechnology = async () => {
    if (!editTechnologyId.value || !editTechnologyName.value.trim() || !editTechnologySvg.value.trim() || !editTechnologyDescription.value.trim())
        return;

    isLoading.value = true;

    try {
        await axios.put(
            route('dashboard.api.technologies.update', {
                technology: editTechnologyId.value,
            }),
            {
                name: editTechnologyName.value.trim(),
                type: editTechnologyType.value,
                svg_icon: editTechnologySvg.value.trim(),
                locale: locale.value,
                description: editTechnologyDescription.value.trim(),
            },
        );

        resetEditTechnologyForm();
        isEditTechnologyDialogOpen.value = false;
        toast.success('Technologie mise à jour avec succès');

        window.location.reload();
    } catch (err) {
        console.error('Erreur lors de la mise à jour de la technologie:', err);

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
        isLoading.value = false;
    }
};

const confirmDeleteTechnology = (technology: TechnologyWithCreationsCount) => {
    technologyToDelete.value = technology;
    isDeleteTechnologyDialogOpen.value = true;
};

const deleteTechnology = async () => {
    if (!technologyToDelete.value) return;

    isLoading.value = true;

    try {
        await axios.delete(
            route('dashboard.api.technologies.destroy', {
                technology: technologyToDelete.value.id,
            }),
        );

        isDeleteTechnologyDialogOpen.value = false;
        toast.success('Technologie supprimée avec succès');
        window.location.reload();
    } catch (err) {
        console.error('Erreur lors de la suppression de la technologie:', err);
        toast.error('Impossible de supprimer cette technologie');
    } finally {
        isLoading.value = false;
    }
};

const openEditTechnologyForm = (technology: TechnologyWithCreationsCount) => {
    editTechnologyId.value = technology.id;
    editTechnologyName.value = technology.name;
    editTechnologyType.value = technology.type;
    editTechnologySvg.value = technology.svg_icon;
    editTechnologyDescription.value = getTechnologyDescription(technology);
    isEditTechnologyDialogOpen.value = true;
};

const resetTechnologyForm = () => {
    newTechnologyName.value = '';
    newTechnologyType.value = 'framework';
    newTechnologySvg.value = '';
    newTechnologyDescription.value = '';
};

const resetEditTechnologyForm = () => {
    editTechnologyId.value = null;
    editTechnologyName.value = '';
    editTechnologyType.value = '';
    editTechnologySvg.value = '';
    editTechnologyDescription.value = '';
};

// Méthodes pour les expériences
const createExperience = async () => {
    if (!newExperienceTechnologyId.value || !newExperienceDescription.value.trim()) return;

    isLoading.value = true;

    try {
        await axios.post(route('dashboard.api.technology-experiences.store'), {
            technology_id: newExperienceTechnologyId.value,
            locale: locale.value,
            description: newExperienceDescription.value.trim(),
        });

        resetExperienceForm();
        isAddExperienceDialogOpen.value = false;
        toast.success('Expérience créée avec succès');

        window.location.reload();
    } catch (err) {
        console.error("Erreur lors de la création de l'expérience:", err);

        let errorMessage = 'Impossible de créer cette expérience';
        if (axios.isAxiosError(err) && err.response?.data?.errors) {
            const errors = err.response.data.errors;
            const firstError = Object.values(errors)[0] as string[];
            if (firstError && firstError.length > 0) {
                errorMessage = firstError[0];
            }
        }

        toast.error(errorMessage);
    } finally {
        isLoading.value = false;
    }
};

const updateExperience = async () => {
    if (!editExperienceId.value || !editExperienceTechnologyId.value || !editExperienceDescription.value.trim()) return;

    isLoading.value = true;

    try {
        await axios.put(
            route('dashboard.api.technology-experiences.update', {
                technology_experience: editExperienceId.value,
            }),
            {
                technology_id: editExperienceTechnologyId.value,
                locale: locale.value,
                description: editExperienceDescription.value.trim(),
            },
        );

        resetEditExperienceForm();
        isEditExperienceDialogOpen.value = false;
        toast.success('Expérience mise à jour avec succès');

        window.location.reload();
    } catch (err) {
        console.error("Erreur lors de la mise à jour de l'expérience:", err);

        let errorMessage = 'Impossible de mettre à jour cette expérience';
        if (axios.isAxiosError(err) && err.response?.data?.errors) {
            const errors = err.response.data.errors;
            const firstError = Object.values(errors)[0] as string[];
            if (firstError && firstError.length > 0) {
                errorMessage = firstError[0];
            }
        }

        toast.error(errorMessage);
    } finally {
        isLoading.value = false;
    }
};

const confirmDeleteExperience = (experience: TechnologyExperience) => {
    experienceToDelete.value = experience;
    isDeleteExperienceDialogOpen.value = true;
};

const deleteExperience = async () => {
    if (!experienceToDelete.value) return;

    isLoading.value = true;

    try {
        await axios.delete(
            route('dashboard.api.technology-experiences.destroy', {
                technology_experience: experienceToDelete.value.id,
            }),
        );

        isDeleteExperienceDialogOpen.value = false;
        toast.success('Expérience supprimée avec succès');
        window.location.reload();
    } catch (err) {
        console.error("Erreur lors de la suppression de l'expérience:", err);
        toast.error('Impossible de supprimer cette expérience');
    } finally {
        isLoading.value = false;
    }
};

const openEditExperienceForm = (experience: TechnologyExperience) => {
    editExperienceId.value = experience.id;
    editExperienceTechnologyId.value = experience.technology_id;
    editExperienceDescription.value = getExperienceDescription(experience);
    isEditExperienceDialogOpen.value = true;
};

const resetExperienceForm = () => {
    newExperienceTechnologyId.value = null;
    newExperienceDescription.value = '';
};

const resetEditExperienceForm = () => {
    editExperienceId.value = null;
    editExperienceTechnologyId.value = null;
    editExperienceDescription.value = '';
};

// Récupérer l'expérience pour une technologie donnée
const getExperienceForTechnology = (technologyId: number): TechnologyExperience | undefined => {
    return props.technologyExperiences.find((exp) => exp.technology_id === technologyId);
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Technologies',
        href: '#',
    },
    {
        title: 'Expériences',
        href: route('dashboard.technology-experiences.index', undefined, false),
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Expériences technologiques" />
        <div class="px-5 py-6">
            <Heading title="Expériences technologiques" description="Gérez vos expériences avec différentes technologies et frameworks." />

            <div class="my-6 flex flex-col space-y-4 lg:flex-row lg:items-center lg:justify-between lg:space-y-0">
                <div class="flex flex-col space-y-2 lg:flex-row lg:space-y-0 lg:space-x-2">
                    <div class="relative w-full lg:w-64">
                        <Search class="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                        <Input v-model="searchQuery" placeholder="Rechercher une technologie..." class="pl-8" data-form-type="other" />
                    </div>
                    <Select v-model="locale">
                        <SelectTrigger class="w-full lg:w-32">
                            <SelectValue placeholder="Langue" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="fr">Français</SelectItem>
                            <SelectItem value="en">Anglais</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="flex space-x-2">
                    <Button @click="isAddTechnologyDialogOpen = true">
                        <Plus class="mr-2 h-4 w-4" />
                        Nouvelle technologie
                    </Button>
                    <Button @click="isAddExperienceDialogOpen = true">
                        <Plus class="mr-2 h-4 w-4" />
                        Nouvelle expérience
                    </Button>
                </div>
            </div>

            <div v-if="filteredTechnologies.length === 0" class="my-12 text-center">
                <Code class="text-muted-foreground mx-auto h-16 w-16" />
                <p class="text-muted-foreground mt-2">Aucune technologie trouvée</p>
            </div>

            <div v-else class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                <div
                    v-for="technology in filteredTechnologies"
                    :key="technology.id"
                    class="border-border bg-card flex flex-col overflow-hidden rounded-lg border shadow-sm"
                >
                    <div class="p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="mr-3 h-10 w-10" v-html="technology.svg_icon"></div>
                                <div>
                                    <h3 class="text-lg font-semibold">{{ technology.name }}</h3>
                                    <Badge variant="outline">{{ getTechnologyTypeLabel(technology.type) }}</Badge>
                                </div>
                            </div>
                            <div class="flex space-x-1">
                                <Button variant="ghost" size="icon" @click="openEditTechnologyForm(technology)">
                                    <Edit class="h-4 w-4" />
                                </Button>
                                <Button variant="ghost" size="icon" @click="confirmDeleteTechnology(technology)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>

                        <p class="text-muted-foreground mt-2 text-sm">
                            {{ getTechnologyDescription(technology) }}
                        </p>

                        <div class="text-muted-foreground mt-4 text-xs">Utilisée dans {{ technology.creations_count }} création(s)</div>
                    </div>

                    <div class="border-border mt-auto border-t">
                        <div class="bg-muted/50 p-4">
                            <div class="flex items-center justify-between">
                                <h4 class="mb-2 text-sm font-medium">Mon expérience</h4>
                                <div class="flex space-x-1">
                                    <Button
                                        v-if="getExperienceForTechnology(technology.id)"
                                        variant="ghost"
                                        size="icon"
                                        @click="openEditExperienceForm(getExperienceForTechnology(technology.id)!)"
                                    >
                                        <Edit class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        v-if="getExperienceForTechnology(technology.id)"
                                        variant="ghost"
                                        size="icon"
                                        @click="confirmDeleteExperience(getExperienceForTechnology(technology.id)!)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        v-if="!getExperienceForTechnology(technology.id)"
                                        variant="outline"
                                        size="sm"
                                        @click="
                                            newExperienceTechnologyId = technology.id;
                                            isAddExperienceDialogOpen = true;
                                        "
                                    >
                                        <Plus class="mr-1 h-3 w-3" />
                                        Ajouter
                                    </Button>
                                </div>
                            </div>

                            <p v-if="getExperienceForTechnology(technology.id)" class="text-sm">
                                {{ getExperienceDescription(getExperienceForTechnology(technology.id)!) }}
                            </p>
                            <p v-else class="text-muted-foreground text-sm italic">Aucune expérience renseignée</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dialog: Ajouter une technologie -->
        <Dialog v-model:open="isAddTechnologyDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Créer une nouvelle technologie</DialogTitle>
                    <DialogDescription> Ajoutez une nouvelle technologie avec ses informations. </DialogDescription>
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
                        <label class="text-sm font-medium">Icône SVG</label>
                        <Textarea v-model="newTechnologySvg" placeholder="<svg>...</svg>" rows="3" class="max-h-32 break-all" />
                        <div v-if="newTechnologySvg" class="mt-2 flex justify-center rounded-md border p-2">
                            <div class="h-10 w-10" v-html="newTechnologySvg"></div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Description ({{ locale }})</label>
                        <Textarea v-model="newTechnologyDescription" placeholder="Description de la technologie" rows="3" />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="isAddTechnologyDialogOpen = false" :disabled="isLoading"> Annuler </Button>
                    <Button :disabled="!newTechnologyName || !newTechnologySvg || !newTechnologyDescription || isLoading" @click="createTechnology">
                        <Loader2 v-if="isLoading" class="mr-2 h-4 w-4 animate-spin" />
                        Créer
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Dialog: Modifier une technologie -->
        <Dialog v-model:open="isEditTechnologyDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Modifier une technologie</DialogTitle>
                    <DialogDescription> Modifiez les informations de cette technologie. </DialogDescription>
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
                        <label class="text-sm font-medium">Icône SVG</label>
                        <Textarea v-model="editTechnologySvg" placeholder="<svg>...</svg>" rows="3" class="max-h-32 break-all" />
                        <div v-if="editTechnologySvg" class="mt-2 flex justify-center rounded-md border p-2">
                            <div class="h-10 w-10" v-html="editTechnologySvg"></div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Description ({{ locale }})</label>
                        <Textarea v-model="editTechnologyDescription" placeholder="Description de la technologie" rows="3" />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="isEditTechnologyDialogOpen = false" :disabled="isLoading"> Annuler </Button>
                    <Button
                        :disabled="!editTechnologyName || !editTechnologySvg || !editTechnologyDescription || isLoading"
                        @click="updateTechnology"
                    >
                        <Loader2 v-if="isLoading" class="mr-2 h-4 w-4 animate-spin" />
                        Enregistrer
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Dialog: Ajouter une expérience -->
        <Dialog v-model:open="isAddExperienceDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Ajouter une expérience</DialogTitle>
                    <DialogDescription> Décrivez votre expérience avec cette technologie. </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Technologie</label>
                        <Select v-model="newExperienceTechnologyId">
                            <SelectTrigger>
                                <SelectValue placeholder="Sélectionner une technologie" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="tech in props.technologies" :key="tech.id" :value="tech.id">
                                    {{ tech.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Description ({{ locale }})</label>
                        <Textarea v-model="newExperienceDescription" placeholder="Décrivez votre expérience avec cette technologie" rows="5" />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="isAddExperienceDialogOpen = false" :disabled="isLoading"> Annuler </Button>
                    <Button :disabled="!newExperienceTechnologyId || !newExperienceDescription || isLoading" @click="createExperience">
                        <Loader2 v-if="isLoading" class="mr-2 h-4 w-4 animate-spin" />
                        Ajouter
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Dialog: Modifier une expérience -->
        <Dialog v-model:open="isEditExperienceDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Modifier une expérience</DialogTitle>
                    <DialogDescription> Mettez à jour votre expérience avec cette technologie. </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Technologie</label>
                        <Select v-model="editExperienceTechnologyId">
                            <SelectTrigger>
                                <SelectValue placeholder="Sélectionner une technologie" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="tech in props.technologies" :key="tech.id" :value="tech.id">
                                    {{ tech.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Description ({{ locale }})</label>
                        <Textarea v-model="editExperienceDescription" placeholder="Décrivez votre expérience avec cette technologie" rows="5" />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="isEditExperienceDialogOpen = false" :disabled="isLoading"> Annuler </Button>
                    <Button :disabled="!editExperienceTechnologyId || !editExperienceDescription || isLoading" @click="updateExperience">
                        <Loader2 v-if="isLoading" class="mr-2 h-4 w-4 animate-spin" />
                        Enregistrer
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Dialog: Confirmer la suppression d'une technologie -->
        <AlertDialog v-model:open="isDeleteTechnologyDialogOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Supprimer cette technologie ?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Cette action est irréversible. Cette technologie sera définitivement supprimée de la base de données.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="isDeleteTechnologyDialogOpen = false" :disabled="isLoading"> Annuler </AlertDialogCancel>
                    <AlertDialogAction
                        @click="deleteTechnology"
                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        :disabled="isLoading"
                    >
                        <Loader2 v-if="isLoading" class="mr-2 h-4 w-4 animate-spin" />
                        Supprimer
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Dialog: Confirmer la suppression d'une expérience -->
        <AlertDialog v-model:open="isDeleteExperienceDialogOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Supprimer cette expérience ?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Cette action est irréversible. Cette expérience sera définitivement supprimée de la base de données.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="isDeleteExperienceDialogOpen = false" :disabled="isLoading"> Annuler </AlertDialogCancel>
                    <AlertDialogAction
                        @click="deleteExperience"
                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        :disabled="isLoading"
                    >
                        <Loader2 v-if="isLoading" class="mr-2 h-4 w-4 animate-spin" />
                        Supprimer
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
