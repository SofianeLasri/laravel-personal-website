<script setup lang="ts">
import CreationDraftScreenshots from '@/components/CreationDraftScreenshots.vue';
import Heading from '@/components/Heading.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import MarkdownEditor from '@/components/MarkdownEditor.vue';
import PictureInput from '@/components/PictureInput.vue';
import { Button } from '@/components/ui/button';
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/components/ui/toast';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem, CreationDraftWithTranslations, CreationType } from '@/types';
import { creationTypeLabels, getTypeLabel } from '@/utils/creationTypes';
import { Head } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import axios from 'axios';
import { useForm } from 'vee-validate';
import { computed, onMounted, ref } from 'vue';
import * as z from 'zod';

const { toast } = useToast();

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

const props = defineProps<{
    creationDraft?: CreationDraftWithTranslations;
}>();

const creationTypes = Object.keys(creationTypeLabels) as CreationType[];
const isSubmitting = ref(false);
const currentCreationDraft = ref<CreationDraftWithTranslations | null>(null);

if (props.creationDraft) {
    currentCreationDraft.value = props.creationDraft;
}

const locale = 'fr';
const localeValue = computed(() => locale);

const getOriginalCreationId = (): number | null => {
    const url = new URL(window.location.href);
    const creationId = url.searchParams.get('creation-id');
    return creationId ? parseInt(creationId) : null;
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
        locale: z.enum(['fr', 'en'], {
            errorMap: () => ({ message: 'La langue est requise' }),
        }),
        short_description_content: z
            .string()
            .max(160, 'La description courte ne doit pas dépasser 160 caractères')
            .min(1, 'La description courte est requise'),
        full_description_content: z.string().min(1, 'La description complète est requise'),
        started_at: z.string().min(1, 'La date de début est requise'),
        ended_at: z.string().nullable(),
    }),
);

let shortDescriptionContent = '';
let fullDescriptionContent = '';

if (currentCreationDraft.value?.short_description_translation_key) {
    const translations = currentCreationDraft.value.short_description_translation_key.translations;
    shortDescriptionContent = translations.find((t) => t.locale === locale)?.text || '';
}

if (currentCreationDraft.value?.full_description_translation_key) {
    const translations = currentCreationDraft.value.full_description_translation_key.translations;
    fullDescriptionContent = translations.find((t) => t.locale === locale)?.text || '';
}

const { isFieldDirty, handleSubmit } = useForm({
    validationSchema: formSchema,
    initialValues: {
        name: currentCreationDraft.value?.name ?? '',
        slug: currentCreationDraft.value?.slug ?? '',
        logo_id: currentCreationDraft.value?.logo_id ?? null,
        cover_image_id: currentCreationDraft.value?.cover_image_id ?? null,
        external_url: currentCreationDraft.value?.external_url ?? '',
        source_code_url: currentCreationDraft.value?.source_code_url ?? '',
        type: currentCreationDraft.value?.type ?? creationTypes[0],
        locale: locale,
        short_description_content: shortDescriptionContent,
        full_description_content: fullDescriptionContent,
        started_at: currentCreationDraft.value?.started_at ?? today,
        ended_at: currentCreationDraft.value?.ended_at ?? null,
    },
});

const onSubmit = handleSubmit(async (formValues) => {
    isSubmitting.value = true;

    try {
        const payload = {
            ...formValues,
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

            const url = new URL(window.location.href);
            if (originalCreationId.value) {
                url.searchParams.delete('creation-id');
            }
            url.searchParams.set('draft-id', response.data.id.toString());
            window.history.replaceState({}, '', url.toString());

            currentCreationDraft.value = response.data;
        }

        toast({
            title: 'Succès',
            description: successMessage,
            variant: 'default',
        });
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

        toast({
            title: 'Erreur',
            description: errorMessage,
            variant: 'destructive',
        });
    } finally {
        isSubmitting.value = false;
    }
});

onMounted(() => {
    const url = new URL(window.location.href);
    const creationId = url.searchParams.get('creation-id');

    if (creationId && currentCreationDraft.value) {
        url.searchParams.delete('creation-id');
        url.searchParams.set('draft-id', currentCreationDraft.value.id.toString());
        window.history.replaceState({}, '', url.toString());
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

                        <Select :default-value="'fr'">
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
                            <Input v-bind="componentField" type="text" placeholder="Nom de la création" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>
                <FormField v-slot="{ componentField }" name="slug" :validate-on-blur="!isFieldDirty">
                    <FormItem>
                        <FormLabel>Slug de la création</FormLabel>
                        <FormControl>
                            <Input v-bind="componentField" type="text" placeholder="Slug de la création" />
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
                            <Input v-bind="componentField" type="date" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>
                <FormField v-slot="{ componentField }" name="ended_at">
                    <FormItem>
                        <FormLabel>Date de fin (optionnelle)</FormLabel>
                        <FormControl>
                            <Input v-bind="componentField" type="date" />
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
                                <Input v-bind="componentField" type="text" placeholder="URL du projet" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                    <FormField v-slot="{ componentField }" name="source_code_url">
                        <FormItem>
                            <FormLabel>URL du code source</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" type="text" placeholder="URL du code source" />
                            </FormControl>
                            <FormMessage />
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
        </form>

        <div v-if="currentCreationDraft?.id" class="border-t border-border">
            <div class="border-t border-border px-5 py-6">
                <CreationDraftScreenshots :creation-draft-id="currentCreationDraft.id || null" :locale="localeValue" />
            </div>
        </div>
    </AppLayout>
</template>
