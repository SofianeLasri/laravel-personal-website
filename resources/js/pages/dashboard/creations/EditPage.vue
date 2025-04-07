<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import PictureInput from '@/components/PictureInput.vue';
import { Button } from '@/components/ui/button';
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem, CreationDraftWithTranslations, CreationType } from '@/types';
import { creationTypeLabels, getTypeLabel } from '@/utils/creationTypes';
import { Head } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import {
    BaseKit,
    Blockquote,
    Bold,
    BulletList,
    CodeBlock,
    EchoEditor,
    locale as editorLocale,
    Italic,
    OrderedList,
    Strike,
    Underline,
} from 'echo-editor';
//import '../../../../css/echo-editor.css'
import { Code, Table } from 'lucide';
import { useForm } from 'vee-validate';
import * as z from 'zod';

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

interface Props {
    creationDraft?: CreationDraftWithTranslations;
}

const props = defineProps<Props>();

const creationTypes = Object.keys(creationTypeLabels) as CreationType[];

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
        short_description_content: z.string().max(160).nullable(),
    }),
);

const locale = 'fr';

let shortDescriptionContent = '';

if (props.creationDraft?.short_description_translation_key) {
    const translations = props.creationDraft.short_description_translation_key.translations;
    shortDescriptionContent = translations.find((t) => t.locale === locale)?.text || '';
}

const { isFieldDirty, handleSubmit } = useForm({
    validationSchema: formSchema,
    initialValues: {
        name: props.creationDraft?.name ?? '',
        slug: props.creationDraft?.slug ?? '',
        logo_id: props.creationDraft?.logo_id ?? null,
        cover_image_id: props.creationDraft?.cover_image_id ?? null,
        external_url: props.creationDraft?.external_url ?? '',
        source_code_url: props.creationDraft?.source_code_url ?? '',
        type: props.creationDraft?.type ?? creationTypes[0],
        locale: locale,
        short_description_content: shortDescriptionContent,
    },
});

// Debug list all variables in the console
const onSubmit = handleSubmit((values) => {
    console.log('Form values:', values);
    // Handle form submission here
});

const editorExtensions = [
    BaseKit.configure({
        Bold,
        Italic,
        Underline,
        Strike,
        BulletList,
        OrderedList,
        Blockquote,
        CodeBlock,
        Table,
        Code,
    }),
];

editorLocale.setLang('en');
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
                    </FormItem>
                </FormField>
                <FormField v-slot="{ componentField }" name="slug" :validate-on-blur="!isFieldDirty">
                    <FormItem>
                        <FormLabel>Slug de la création</FormLabel>
                        <FormControl>
                            <Input v-bind="componentField" type="text" placeholder="Slug de la création" />
                        </FormControl>
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
                                <PictureInput v-bind="componentField" :model-value="props.creationDraft?.logo_id ?? undefined" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                    <FormField v-slot="{ componentField }" name="cover_image_id">
                        <FormItem>
                            <FormLabel>Image de couverture</FormLabel>
                            <FormControl>
                                <PictureInput v-bind="componentField" :model-value="props.creationDraft?.cover_image_id ?? undefined" />
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
                        </FormItem>
                    </FormField>
                    <FormField v-slot="{ componentField }" name="external_url" :validate-on-blur="!isFieldDirty">
                        <FormItem>
                            <FormLabel>URL du projet (externe & publique)</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" type="text" placeholder="URL du projet" />
                            </FormControl>
                        </FormItem>
                    </FormField>
                    <FormField v-slot="{ componentField }" name="source_code_url">
                        <FormItem>
                            <FormLabel>URL du code source</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" type="text" placeholder="URL du code source" />
                            </FormControl>
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
                    </FormItem>
                </FormField>
            </div>

            <div class="mb-4 rounded-lg border bg-card text-card-foreground shadow-sm">
                <echo-editor :extensions="editorExtensions" output="html" min-height="512" :hide-toolbar="false" :hide-menubar="true"></echo-editor>
            </div>

            <Button type="submit"> Submit</Button>
        </form>
    </AppLayout>
</template>

<style scoped></style>
