<script setup lang="ts">
import CreationDraftFeatures from '@/components/dashboard/CreationDraftFeatures.vue';
import CreationDraftPeople from '@/components/dashboard/CreationDraftPeople.vue';
import CreationDraftScreenshots from '@/components/dashboard/CreationDraftScreenshots.vue';
import CreationDraftTags from '@/components/dashboard/CreationDraftTags.vue';
import CreationDraftTechnologies from '@/components/dashboard/CreationDraftTechnologies.vue';
import CreationDraftVideos from '@/components/dashboard/CreationDraftVideos.vue';
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
import { Checkbox } from '@/components/ui/checkbox';
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useRoute } from '@/composables/useRoute';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem, CreationDraftWithTranslations, CreationType, TranslationKey } from '@/types';
import { creationTypeLabels, getTypeLabel } from '@/utils/creationTypes';
import { Head, router, usePage } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import axios from 'axios';
import slugify from 'slugify';
import { useForm } from 'vee-validate';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import * as z from 'zod';

const props = defineProps<{
    creationDraft?: CreationDraftWithTranslations;
}>();
const route = useRoute();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Créations',
        href: '#',
    },
    {
        title: 'Éditeur',
        href: route('dashboard.creations.edit', undefined, false),
    },
];

const creationTypes = Object.keys(creationTypeLabels) as CreationType[];
const isSubmitting = ref(false);
const isPublishing = ref(false);
const currentCreationDraft = ref<CreationDraftWithTranslations | null>(null);

if (props.creationDraft) {
    currentCreationDraft.value = props.creationDraft;
}

const page = usePage();
const locale = ref<'fr' | 'en'>('fr');
const localeValue = computed(() => locale.value);

const showLocaleChangeDialog = ref(false);
const pendingLocale = ref<string | null>(null);

const getOriginalCreationId = (): number | null => {
    // To avoid errors on ssr
    let url = new URL(page.props.ziggy.location);
    if (typeof window !== 'undefined' && window.location.href) {
        url = new URL(window.location.href);
    }

    const creationId = url.searchParams.get('creation-id');
    return creationId ? parseInt(creationId, 10) : null;
};

const originalCreationId = ref(getOriginalCreationId());

const today = new Date().toISOString().split('T')[0];

const formSchema = toTypedSchema(
    z.object({
        name: z.string().min(1, 'Le nom est requis'),
        slug: z.string().min(1, 'Le slug est requis'),
        logo_id: z.number().nullable(),
        cover_image_id: z.number().nullable(),
        external_url: z.string().nullable(),
        source_code_url: z.string().nullable(),
        type: z.string(),
        locale: z.enum(['fr', 'en']).default('fr'),
        short_description_content: z
            .string()
            .max(160, 'La description courte ne doit pas dépasser 160 caractères')
            .min(1, 'La description courte est requise'),
        full_description_content: z.string().min(1, 'La description complète est requise'),
        featured: z.boolean().default(false),
        started_at: z.string().min(1, 'La date de début est requise'),
        ended_at: z.string().nullable(),
    }),
);

const getContentForLocale = (translationKey: TranslationKey | undefined, targetLocale: string) => {
    if (!translationKey) return '';
    const translations = translationKey.translations;
    return translations.find((t) => t.locale === targetLocale)?.text ?? '';
};

let shortDescriptionContent = '';
let fullDescriptionContent = '';

if (currentCreationDraft.value?.short_description_translation_key) {
    shortDescriptionContent = getContentForLocale(currentCreationDraft.value.short_description_translation_key, locale.value);
}

if (currentCreationDraft.value?.full_description_translation_key) {
    fullDescriptionContent = getContentForLocale(currentCreationDraft.value.full_description_translation_key, locale.value);
}

const { isFieldDirty, handleSubmit, setFieldValue, meta } = useForm({
    validationSchema: formSchema,
    initialValues: {
        name: currentCreationDraft.value?.name ?? '',
        slug: currentCreationDraft.value?.slug ?? '',
        logo_id: currentCreationDraft.value?.logo_id ?? null,
        cover_image_id: currentCreationDraft.value?.cover_image_id ?? null,
        external_url: currentCreationDraft.value?.external_url ?? '',
        source_code_url: currentCreationDraft.value?.source_code_url ?? '',
        type: currentCreationDraft.value?.type ?? creationTypes[0],
        locale: locale.value,
        short_description_content: shortDescriptionContent,
        full_description_content: fullDescriptionContent,
        featured: currentCreationDraft.value?.featured ?? false,
        started_at: currentCreationDraft.value?.started_at ? currentCreationDraft.value.started_at.split('T')[0] : today,
        ended_at: currentCreationDraft.value?.ended_at ? currentCreationDraft.value.ended_at.split('T')[0] : null,
    },
});

const hasUnsavedChanges = computed(() => {
    return meta.value.dirty;
});

const generateSlug = (name: string): string => {
    return slugify(name, {
        lower: true,
        strict: true,
        trim: true,
    });
};

const nameField = ref(currentCreationDraft.value?.name ?? '');

watch(nameField, (newName) => {
    if (newName) {
        setFieldValue('slug', generateSlug(newName));
    }
});

const updateContentForLocale = (newLocale: string) => {
    if (currentCreationDraft.value) {
        const newShortDesc = getContentForLocale(currentCreationDraft.value.short_description_translation_key, newLocale);

        const newFullDesc = getContentForLocale(currentCreationDraft.value.full_description_translation_key, newLocale);

        setFieldValue('short_description_content', newShortDesc);
        setFieldValue('full_description_content', newFullDesc);
    }

    setFieldValue('locale', newLocale);
    locale.value = newLocale;
};

const handleLocaleChange = (newLocale: string) => {
    if (hasUnsavedChanges.value) {
        pendingLocale.value = newLocale;
        showLocaleChangeDialog.value = true;
    } else {
        updateContentForLocale(newLocale);
    }
};

const confirmLocaleChange = () => {
    if (pendingLocale.value) {
        updateContentForLocale(pendingLocale.value);
        pendingLocale.value = null;
    }
    showLocaleChangeDialog.value = false;
};

const cancelLocaleChange = () => {
    pendingLocale.value = null;
    showLocaleChangeDialog.value = false;
};

const onSubmit = handleSubmit(async (formValues) => {
    isSubmitting.value = true;

    try {
        const processedFormValues = {
            ...formValues,
            ended_at: formValues.ended_at === '' ? null : formValues.ended_at,
        };

        const payload = {
            ...processedFormValues,
            original_creation_id: originalCreationId.value,
        };

        let response;
        let successMessage: string;

        if (currentCreationDraft.value?.id) {
            await axios.put(
                route('dashboard.api.creation-drafts.update', {
                    creation_draft: currentCreationDraft.value.id,
                }),
                payload,
            );
            successMessage = 'Brouillon mis à jour avec succès';
        } else {
            response = await axios.post(route('dashboard.api.creation-drafts.store'), payload);
            successMessage = 'Brouillon créé avec succès';

            const url = new URL(page.props.ziggy.location);
            if (originalCreationId.value) {
                url.searchParams.delete('creation-id');
            }
            url.searchParams.set('draft-id', response.data.id.toString());
            if (typeof window !== 'undefined') {
                window.history.replaceState({}, '', url.toString());
            }

            currentCreationDraft.value = response.data;
        }

        toast.success(successMessage);
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

const publishDraft = async () => {
    if (!currentCreationDraft.value?.id) return;

    isPublishing.value = true;

    try {
        await axios.post(route('dashboard.api.creations.store'), {
            draft_id: currentCreationDraft.value.id,
        });

        toast.success('Votre création a été publiée avec succès');

        router.visit(route('dashboard.creations.index'));
    } catch (error) {
        console.error('Erreur lors de la publication:', error);

        let errorMessage = 'Une erreur est survenue lors de la publication';

        if (axios.isAxiosError(error) && error.response) {
            if (error.response.status === 422) {
                errorMessage = 'Le brouillon contient des erreurs qui empêchent sa publication';
            } else {
                errorMessage = `Erreur ${error.response.status}: ${error.response.statusText}`;
            }
        }

        toast.error(errorMessage);
    } finally {
        isPublishing.value = false;
    }
};

onMounted(() => {
    const url = new URL(page.props.ziggy.location);
    const creationId = url.searchParams.get('creation-id');

    if (creationId && currentCreationDraft.value) {
        url.searchParams.delete('creation-id');
        url.searchParams.set('draft-id', currentCreationDraft.value.id.toString());
        if (typeof window !== 'undefined') {
            window.history.replaceState({}, '', url.toString());
        }
    }
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Éditeur" />
        <form class="px-5 py-6" @submit="onSubmit">
            <Heading title="Éditeur" description="Créer ou modifier une création." />

            <!-- Locale -->
            <div class="mb-8 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <FormField v-slot="{ componentField }" name="locale">
                    <FormItem v-bind="componentField">
                        <FormLabel>Langue</FormLabel>

                        <Select v-model="locale" @update:model-value="handleLocaleChange">
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

            <HeadingSmall
                title="Informations de base"
                description="Ces informations permettent d'identifier la création, son nom et son slug ne sont pas traductibles."
            />

            <!-- Nom & slug -->
            <div class="my-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <FormField v-slot="{ componentField }" name="name" :validate-on-blur="!isFieldDirty">
                    <FormItem>
                        <FormLabel>Nom de la création</FormLabel>
                        <FormControl>
                            <Input v-bind="componentField" v-model="nameField" type="text" placeholder="Nom de la création" data-form-type="other" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>
                <FormField v-slot="{ componentField }" name="slug" :validate-on-blur="!isFieldDirty">
                    <FormItem>
                        <FormLabel>Slug de la création</FormLabel>
                        <FormControl>
                            <Input v-bind="componentField" type="text" placeholder="Slug de la création" data-form-type="other" />
                        </FormControl>
                        <FormDescription> Généré automatiquement à partir du nom. Peut être modifié manuellement si nécessaire. </FormDescription>
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
                        <FormLabel>Date de fin (optionnelle)</FormLabel>
                        <FormControl>
                            <Input v-bind="componentField" type="date" data-form-type="other" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>
            </div>

            <!-- Images de couverture, Type && Url -->
            <div class="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="flex flex-col gap-4">
                    <FormField v-slot="{ componentField }" name="logo_id">
                        <FormItem>
                            <FormLabel>Logo</FormLabel>
                            <FormControl>
                                <PictureInput v-bind="componentField" :model-value="currentCreationDraft?.logo_id ?? undefined" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                    <FormField v-slot="{ componentField }" name="cover_image_id">
                        <FormItem>
                            <FormLabel>Image de couverture</FormLabel>
                            <FormControl>
                                <PictureInput v-bind="componentField" :model-value="currentCreationDraft?.cover_image_id ?? undefined" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>
                <div class="flex flex-col gap-4">
                    <FormField v-slot="{ componentField }" name="type" :validate-on-blur="!isFieldDirty">
                        <FormItem>
                            <FormLabel>Type de création</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Sélectionner un type de création" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="type in creationTypes" :key="type" :value="type">
                                            {{ getTypeLabel(type) }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                    <FormField v-slot="{ componentField }" name="external_url" :validate-on-blur="!isFieldDirty">
                        <FormItem>
                            <FormLabel>URL du projet (externe & publique)</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" type="text" placeholder="URL du projet" data-form-type="other" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                    <FormField v-slot="{ componentField }" name="source_code_url">
                        <FormItem>
                            <FormLabel>URL du code source</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" type="text" placeholder="URL du code source" data-form-type="other" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                    <FormField v-slot="{ componentField }" type="checkbox" name="featured">
                        <FormItem class="flex flex-row items-start space-y-0 gap-x-3">
                            <FormControl>
                                <Checkbox v-bind="componentField" />
                            </FormControl>
                            <div class="space-y-1 leading-none">
                                <FormLabel>Création mise en avant</FormLabel>
                                <FormDescription> Si cette case est cochée, la création sera mise en avant sur la page d'accueil. </FormDescription>
                                <FormMessage />
                            </div>
                        </FormItem>
                    </FormField>
                </div>
            </div>

            <div class="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <FormField v-slot="{ componentField }" name="short_description_content">
                    <FormItem>
                        <FormLabel>Courte description</FormLabel>
                        <FormControl>
                            <Textarea placeholder="Courte description" v-bind="componentField" />
                        </FormControl>
                        <FormDescription>
                            La description courte sera utilisée pour le référencement (SEO) ainsi que pour la présentation du projet sur le site et
                            dans les intégrations embeds.
                        </FormDescription>
                        <FormMessage />
                    </FormItem>
                </FormField>
            </div>

            <div class="mb-4">
                <FormField v-slot="{ componentField }" name="full_description_content">
                    <FormItem>
                        <FormLabel>Description</FormLabel>
                        <FormControl>
                            <MarkdownEditor v-bind="componentField" placeholder="Commencez à écrire..." />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>
            </div>

            <div class="flex space-x-4">
                <Button type="submit" :disabled="isSubmitting">
                    <span v-if="isSubmitting" class="mr-2">
                        <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                    </span>
                    {{ currentCreationDraft?.id ? 'Mettre à jour' : 'Créer' }}
                </Button>

                <Button
                    v-if="currentCreationDraft?.id"
                    type="button"
                    variant="default"
                    :disabled="isPublishing || isSubmitting"
                    @click="publishDraft"
                >
                    <span v-if="isPublishing" class="mr-2">
                        <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                    </span>
                    Publier
                </Button>
            </div>
        </form>

        <div v-if="currentCreationDraft?.id" class="border-t">
            <div class="border-t px-5 py-6">
                <CreationDraftScreenshots :creation-draft-id="currentCreationDraft.id" :locale="localeValue" />
            </div>

            <div class="border-t px-5 py-6">
                <CreationDraftVideos :creation-draft-id="currentCreationDraft.id" />
            </div>

            <div class="border-t px-5 py-6">
                <CreationDraftFeatures :creation-draft-id="currentCreationDraft.id" :locale="localeValue" />
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="border-t px-5 py-6">
                    <CreationDraftPeople :creation-draft-id="currentCreationDraft.id" />
                </div>
                <div class="border-t px-5 py-6">
                    <CreationDraftTags :creation-draft-id="currentCreationDraft.id" />
                </div>
            </div>

            <div class="border-t px-5 py-6">
                <CreationDraftTechnologies :creation-draft-id="currentCreationDraft.id" :locale="localeValue" />
            </div>
        </div>

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
    </AppLayout>
</template>
