<script setup lang="ts">
import Heading from '@/components/dashboard/Heading.vue';
import HeadingSmall from '@/components/dashboard/HeadingSmall.vue';
import MarkdownEditor from '@/components/dashboard/MarkdownEditor.vue';
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
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem, Technology } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import axios from 'axios';
import { format } from 'date-fns';
import { Loader2, Plus, Search } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import * as z from 'zod';

// Types
interface Experience {
    id: number;
    organization_name: string;
    logo_id: number | null;
    logo?: {
        id: number;
        path_original: string;
    };
    type: 'formation' | 'emploi';
    location: string;
    website_url: string | null;
    started_at: string;
    ended_at: string | null;
    title_translation_key: {
        id: number;
        key: string;
        translations: Array<{
            id: number;
            translation_key_id: number;
            locale: string;
            text: string;
        }>;
    };
    short_description_translation_key: {
        id: number;
        key: string;
        translations: Array<{
            id: number;
            translation_key_id: number;
            locale: string;
            text: string;
        }>;
    };
    full_description_translation_key: {
        id: number;
        key: string;
        translations: Array<{
            id: number;
            translation_key_id: number;
            locale: string;
            text: string;
        }>;
    };
    technologies: Technology[];
}

// Props
const props = defineProps<{
    experience?: Experience;
    technologies: Technology[];
}>();

// Breadcrumbs
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Expériences',
        href: route('dashboard.experiences.index', undefined, false),
    },
    {
        title: props.experience ? 'Modifier une expérience' : 'Nouvelle expérience',
        href: props.experience
            ? route('dashboard.experiences.edit', props.experience.id, false)
            : route('dashboard.experiences.create', undefined, false),
    },
];

// État local
const locale = ref<'fr' | 'en'>('fr');
const isSubmitting = ref(false);
const showLocaleChangeDialog = ref(false);
const pendingLocale = ref<string | null>(null);
const modalTechOpen = ref(false);
const searchTechQuery = ref('');
const selectedTechnologies = ref<Technology[]>(props.experience?.technologies || []);

// Obtenir les traductions
const getTranslation = (translationKey: any, targetLocale: string) => {
    if (!translationKey || !translationKey.translations) return '';
    const translation = translationKey.translations.find((t: any) => t.locale === targetLocale);
    return translation ? translation.text : '';
};

// Obtenir les valeurs initiales pour le formulaire
let titleContent = '';
let shortDescriptionContent = '';
let fullDescriptionContent = '';

if (props.experience) {
    titleContent = getTranslation(props.experience.title_translation_key, locale.value);
    shortDescriptionContent = getTranslation(props.experience.short_description_translation_key, locale.value);
    fullDescriptionContent = getTranslation(props.experience.full_description_translation_key, locale.value);
}

// Définition du schéma de formulaire avec Zod
const formSchema = toTypedSchema(
    z.object({
        title: z.string().min(1, 'Le titre est requis'),
        organization_name: z.string().min(1, "Le nom de l'organisation est requis"),
        logo_id: z.number().nullable(),
        type: z.enum(['formation', 'emploi']),
        location: z.string().min(1, "L'emplacement est requis"),
        website_url: z.string().nullable(),
        locale: z.enum(['fr', 'en']).default('fr'),
        short_description: z.string().min(1, 'La description courte est requise'),
        full_description: z.string().min(1, 'La description complète est requise'),
        started_at: z.string().min(1, 'La date de début est requise'),
        ended_at: z.string().nullable(),
    }),
);

// Initialisation du formulaire
const { isFieldDirty, handleSubmit, setFieldValue, meta } = useForm({
    validationSchema: formSchema,
    initialValues: {
        title: titleContent,
        organization_name: props.experience?.organization_name ?? '',
        logo_id: props.experience?.logo_id ?? null,
        type: props.experience?.type ?? 'emploi',
        location: props.experience?.location ?? '',
        website_url: props.experience?.website_url ?? '',
        locale: locale.value,
        short_description: shortDescriptionContent,
        full_description: fullDescriptionContent,
        started_at: props.experience?.started_at ? format(new Date(props.experience.started_at), 'yyyy-MM-dd') : '',
        ended_at: props.experience?.ended_at ? format(new Date(props.experience.ended_at), 'yyyy-MM-dd') : '',
    },
});

// Vérification des changements non enregistrés
const hasUnsavedChanges = computed(() => {
    return meta.value.dirty;
});

// Gestion du changement de langue
const updateContentForLocale = (newLocale: 'fr' | 'en') => {
    if (props.experience) {
        const newTitle = getTranslation(props.experience.title_translation_key, newLocale);
        const newShortDesc = getTranslation(props.experience.short_description_translation_key, newLocale);
        const newFullDesc = getTranslation(props.experience.full_description_translation_key, newLocale);

        setFieldValue('title', newTitle);
        setFieldValue('short_description', newShortDesc);
        setFieldValue('full_description', newFullDesc);
    }

    setFieldValue('locale', newLocale);
    locale.value = newLocale;
};

const handleLocaleChange = (newLocale: any) => {
    if (hasUnsavedChanges.value) {
        pendingLocale.value = newLocale;
        showLocaleChangeDialog.value = true;
    } else {
        updateContentForLocale(newLocale);
    }
};

const confirmLocaleChange = () => {
    if (pendingLocale.value) {
        updateContentForLocale(pendingLocale.value as 'fr' | 'en');
        pendingLocale.value = null;
    }
    showLocaleChangeDialog.value = false;
};

const cancelLocaleChange = () => {
    pendingLocale.value = null;
    showLocaleChangeDialog.value = false;
};

// Filtrage des technologies
const filteredTechnologies = computed(() => {
    if (!searchTechQuery.value.trim()) return props.technologies;

    const query = searchTechQuery.value.toLowerCase();
    return props.technologies.filter((tech) => tech.name.toLowerCase().includes(query) || tech.type.toLowerCase().includes(query));
});

const isTechnologySelected = (id: number) => {
    return selectedTechnologies.value.some((tech) => tech.id === id);
};

const toggleTechnology = (technology: Technology) => {
    if (isTechnologySelected(technology.id)) {
        selectedTechnologies.value = selectedTechnologies.value.filter((tech) => tech.id !== technology.id);
    } else {
        selectedTechnologies.value.push(technology);
    }
};

// Soumission du formulaire
const onSubmit = handleSubmit(async (formValues) => {
    isSubmitting.value = true;

    try {
        const payload = {
            ...formValues,
            technologies: selectedTechnologies.value.map((tech) => tech.id),
        };

        let successMessage: string;

        if (props.experience?.id) {
            await axios.put(route('dashboard.api.experiences.update', { experience: props.experience.id }), payload);
            successMessage = 'Expérience mise à jour avec succès';
        } else {
            await axios.post(route('dashboard.api.experiences.store'), payload);
            successMessage = 'Expérience créée avec succès';
        }

        toast.success(successMessage);
        router.visit(route('dashboard.experiences.index'));
    } catch (error) {
        console.error('Erreur lors de la soumission du formulaire:', error);

        let errorMessage = 'Une erreur est survenue lors de la sauvegarde';

        if (axios.isAxiosError(error) && error.response) {
            if (error.response.status === 422) {
                errorMessage = 'Le formulaire contient des erreurs';
            } else {
                errorMessage = `Erreur ${error.response.status}: ${error.response.statusText}`;
            }
        }

        toast.error(errorMessage);
    } finally {
        isSubmitting.value = false;
    }
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="experience ? 'Modifier une expérience' : 'Nouvelle expérience'" />
        <form class="px-5 py-6" @submit="onSubmit">
            <Heading
                :title="experience ? 'Modifier une expérience' : 'Nouvelle expérience'"
                description="Créez ou modifiez une expérience professionnelle ou éducative."
            />

            <!-- Langue -->
            <div class="mb-8 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <FormField v-slot="{ componentField }" name="locale">
                    <FormItem v-bind="componentField">
                        <FormLabel>Langue</FormLabel>
                        <Select v-model="locale" @update:modelValue="handleLocaleChange">
                            <FormControl>
                                <SelectTrigger>
                                    <SelectValue placeholder="Sélectionner une langue" />
                                </SelectTrigger>
                            </FormControl>
                            <SelectContent>
                                <SelectItem value="fr">Français</SelectItem>
                                <SelectItem value="en">Anglais</SelectItem>
                            </SelectContent>
                        </Select>
                        <FormDescription> La langue dans laquelle seront enregistrés les champs traductibles. </FormDescription>
                    </FormItem>
                </FormField>
            </div>

            <HeadingSmall title="Informations de base" description="Ces informations permettent d'identifier l'expérience." />

            <!-- Titre & Organisation -->
            <div class="my-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <FormField v-slot="{ componentField }" name="title" :validate-on-blur="!isFieldDirty">
                    <FormItem>
                        <FormLabel>Titre du poste / diplôme</FormLabel>
                        <FormControl>
                            <Input v-bind="componentField" type="text" placeholder="Titre du poste ou du diplôme" data-form-type="other" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>
                <FormField v-slot="{ componentField }" name="organization_name" :validate-on-blur="!isFieldDirty">
                    <FormItem>
                        <FormLabel>Nom de l'organisation</FormLabel>
                        <FormControl>
                            <Input v-bind="componentField" type="text" placeholder="Nom de l'entreprise ou de l'école" data-form-type="other" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>
            </div>

            <!-- Type, location & Url -->
            <div class="my-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <FormField v-slot="{ componentField }" name="type" :validate-on-blur="!isFieldDirty">
                    <FormItem>
                        <FormLabel>Type d'expérience</FormLabel>
                        <FormControl>
                            <Select v-bind="componentField">
                                <SelectTrigger>
                                    <SelectValue placeholder="Sélectionner un type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="emploi">Emploi</SelectItem>
                                    <SelectItem value="formation">Formation</SelectItem>
                                </SelectContent>
                            </Select>
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>
                <FormField v-slot="{ componentField }" name="location" :validate-on-blur="!isFieldDirty">
                    <FormItem>
                        <FormLabel>Lieu</FormLabel>
                        <FormControl>
                            <Input v-bind="componentField" type="text" placeholder="Ville, Pays" data-form-type="other" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>
                <FormField v-slot="{ componentField }" name="website_url">
                    <FormItem>
                        <FormLabel>Site web</FormLabel>
                        <FormControl>
                            <Input v-bind="componentField" type="url" placeholder="https://exemple.com" data-form-type="other" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>
            </div>

            <!-- Dates -->
            <div class="my-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <FormField v-slot="{ componentField }" name="started_at">
                    <FormItem>
                        <FormLabel>Date de début</FormLabel>
                        <FormControl>
                            <Input v-bind="componentField" type="date" data-form-type="other" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>
                <FormField v-slot="{ componentField }" name="ended_at">
                    <FormItem>
                        <FormLabel>Date de fin (laisser vide si en cours)</FormLabel>
                        <FormControl>
                            <Input v-bind="componentField" type="date" data-form-type="other" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>
            </div>

            <!-- Logo -->
            <div class="my-6">
                <FormField v-slot="{ componentField }" name="logo_id">
                    <FormItem>
                        <FormLabel>Logo</FormLabel>
                        <FormControl>
                            <PictureInput v-bind="componentField" :model-value="experience?.logo_id ?? undefined" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>
            </div>

            <HeadingSmall title="Description" description="Décrivez votre expérience professionnelle ou votre formation." />

            <!-- Description courte -->
            <div class="my-4">
                <FormField v-slot="{ componentField }" name="short_description">
                    <FormItem>
                        <FormLabel>Description courte</FormLabel>
                        <FormControl>
                            <Textarea v-bind="componentField" placeholder="Brève description de l'expérience" rows="3" />
                        </FormControl>
                        <FormDescription> Une description concise qui sera affichée dans les listes et cartes. </FormDescription>
                        <FormMessage />
                    </FormItem>
                </FormField>
            </div>

            <!-- Description complète -->
            <div class="my-4">
                <FormField v-slot="{ componentField }" name="full_description">
                    <FormItem>
                        <FormLabel>Description complète</FormLabel>
                        <FormControl>
                            <MarkdownEditor v-bind="componentField" placeholder="Description détaillée de l'expérience..." />
                        </FormControl>
                        <FormDescription>
                            Utilisez Markdown pour formater le texte. Cette description sera affichée dans la vue détaillée.
                        </FormDescription>
                        <FormMessage />
                    </FormItem>
                </FormField>
            </div>

            <!-- Technologies -->
            <div class="my-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-sm font-medium">Technologies utilisées</h3>
                    <Button variant="outline" size="sm" type="button" @click="modalTechOpen = true">
                        <Plus class="mr-2 h-4 w-4" />
                        Gérer les technologies
                    </Button>
                </div>

                <div v-if="selectedTechnologies.length === 0" class="border-border rounded-lg border p-8 text-center">
                    <p class="text-muted-foreground mb-2">Aucune technologie sélectionnée</p>
                    <Button variant="outline" type="button" @click="modalTechOpen = true">
                        <Plus class="mr-2 h-4 w-4" />
                        Ajouter des technologies
                    </Button>
                </div>

                <div v-else class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                    <div v-for="tech in selectedTechnologies" :key="tech.id" class="border-border flex items-center rounded-md border p-2">
                        <div class="mr-2 h-6 w-6" v-html="tech.svg_icon"></div>
                        <span class="text-sm">{{ tech.name }}</span>
                    </div>
                </div>
            </div>

            <!-- Boutons d'action -->
            <div class="mt-8 flex space-x-4">
                <Button type="submit" :disabled="isSubmitting">
                    <span v-if="isSubmitting" class="mr-2">
                        <Loader2 class="h-4 w-4 animate-spin" />
                    </span>
                    {{ experience ? 'Mettre à jour' : 'Créer' }}
                </Button>
                <Button variant="outline" type="button" @click="router.visit(route('dashboard.experiences.index'))"> Annuler </Button>
            </div>
        </form>

        <!-- Dialog de confirmation pour changement de langue -->
        <AlertDialog :open="showLocaleChangeDialog" @update:open="showLocaleChangeDialog = $event">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Modifications non enregistrées</AlertDialogTitle>
                    <AlertDialogDescription>
                        Vous avez des modifications non enregistrées. Si vous changez de langue, ces modifications seront perdues. Souhaitez-vous
                        continuer ?
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="cancelLocaleChange">Annuler</AlertDialogCancel>
                    <AlertDialogAction @click="confirmLocaleChange">Continuer</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Dialog pour gérer les technologies -->
        <Dialog v-model:open="modalTechOpen">
            <DialogContent class="sm:max-w-[600px]">
                <DialogHeader>
                    <DialogTitle>Gérer les technologies</DialogTitle>
                </DialogHeader>

                <div class="py-4">
                    <div class="relative mb-4">
                        <Search class="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                        <Input v-model="searchTechQuery" placeholder="Rechercher une technologie..." class="pl-8" />
                    </div>

                    <ScrollArea class="h-[300px]">
                        <div v-if="filteredTechnologies.length === 0" class="py-8 text-center">
                            <p class="text-muted-foreground text-sm">Aucun résultat trouvé</p>
                        </div>

                        <div v-else class="space-y-2">
                            <div
                                v-for="technology in filteredTechnologies"
                                :key="technology.id"
                                class="hover:bg-muted flex cursor-pointer items-center rounded-md p-3"
                                @click="toggleTechnology(technology)"
                            >
                                <div class="mr-3 h-8 w-8 flex-shrink-0" v-html="technology.svg_icon"></div>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">{{ technology.name }}</p>
                                    <p class="text-muted-foreground text-xs">{{ technology.type }}</p>
                                </div>

                                <div class="ml-2">
                                    <div class="bg-primary/10 h-5 w-5 rounded-full" :class="{ 'bg-primary': isTechnologySelected(technology.id) }">
                                        <svg
                                            v-if="isTechnologySelected(technology.id)"
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            class="text-primary-foreground h-5 w-5"
                                        >
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </ScrollArea>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="modalTechOpen = false">Fermer</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
