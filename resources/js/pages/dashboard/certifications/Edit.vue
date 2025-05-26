<script setup lang="ts">
import Heading from '@/components/dashboard/Heading.vue';
import PictureInput from '@/components/dashboard/PictureInput.vue';
import { Button } from '@/components/ui/button';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import axios from 'axios';
import { Loader2 } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import * as z from 'zod';

// Types
interface Certification {
    id: number;
    name: string;
    level: string;
    score: string;
    date: string;
    link: string;
    picture_id: number;
    picture?: {
        id: number;
        path_original: string;
    };
}

interface Picture {
    id: number;
    filename: string;
    path_original: string;
}

interface Props {
    certification?: Certification | null;
    pictures: Picture[];
}

const props = defineProps<Props>();

// État local
const isSubmitting = ref(false);

// Mode édition ou création
const isEditing = computed(() => !!props.certification);

// Breadcrumbs
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Certifications',
        href: route('dashboard.certifications.index', undefined, false),
    },
    {
        title: isEditing.value ? 'Modifier' : 'Créer',
        href: '#',
    },
];

// Schéma de validation avec Zod
const formSchema = toTypedSchema(
    z.object({
        name: z.string().min(1, 'Le nom est requis'),
        level: z.string().min(1, 'Le niveau est requis'),
        score: z.string().min(1, 'Le score est requis'),
        date: z.string().min(1, 'La date est requise'),
        link: z.string().url('Veuillez entrer une URL valide'),
        picture_id: z
            .number()
            .nullable()
            .refine((val) => val !== null && val > 0, {
                message: 'Veuillez sélectionner une image',
            }),
    }),
);

// Configuration du formulaire
const form = useForm({
    validationSchema: formSchema,
    initialValues: {
        name: props.certification?.name || '',
        level: props.certification?.level || '',
        score: props.certification?.score || '',
        date: props.certification?.date ? props.certification.date.split('T')[0] : '',
        link: props.certification?.link || '',
        picture_id: props.certification?.picture_id || null,
    },
});

// Fonction de soumission
const onSubmit = form.handleSubmit(async (values) => {
    isSubmitting.value = true;

    try {
        if (isEditing.value) {
            // Mise à jour
            await axios.put(route('dashboard.api.certifications.update', { certification: props.certification!.id }), values);
            toast.success('Certification mise à jour avec succès');
        } else {
            // Création
            await axios.post(route('dashboard.api.certifications.store'), values);
            toast.success('Certification créée avec succès');
        }

        // Redirection vers la liste
        router.visit(route('dashboard.certifications.index'));
    } catch (error: any) {
        console.error('Erreur lors de la sauvegarde :', error);

        if (error.response?.status === 422) {
            // Erreurs de validation
            const errors = error.response.data.errors;
            Object.keys(errors).forEach((field) => {
                // Type assertion to ensure field is a valid form field name
                const fieldName = field as keyof typeof values;
                if (fieldName in values) {
                    form.setFieldError(fieldName, errors[field][0]);
                }
            });
        } else {
            toast.error('Erreur lors de la sauvegarde');
        }
    } finally {
        isSubmitting.value = false;
    }
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="isEditing ? 'Modifier la certification' : 'Nouvelle certification'" />

        <div class="px-5 py-6">
            <Heading
                :title="isEditing ? 'Modifier la certification' : 'Nouvelle certification'"
                :description="isEditing ? 'Modifiez les informations de votre certification.' : 'Ajoutez une nouvelle certification à votre profil.'"
            />

            <form @submit="onSubmit" class="mt-8 max-w-2xl space-y-6">
                <!-- Nom de la certification -->
                <FormField v-slot="{ componentField }" name="name">
                    <FormItem>
                        <FormLabel>Nom de la certification</FormLabel>
                        <FormControl>
                            <Input type="text" placeholder="Ex: Laravel Certified Developer" v-bind="componentField" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <!-- Niveau -->
                <FormField v-slot="{ componentField }" name="level">
                    <FormItem>
                        <FormLabel>Niveau</FormLabel>
                        <FormControl>
                            <Input type="text" placeholder="Ex: Beginner, Intermediate, Advanced, Expert" v-bind="componentField" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <!-- Score -->
                <FormField v-slot="{ componentField }" name="score">
                    <FormItem>
                        <FormLabel>Score</FormLabel>
                        <FormControl>
                            <Input type="text" placeholder="Ex: 850/1000 ou 95%" v-bind="componentField" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <!-- Date d'obtention -->
                <FormField v-slot="{ componentField }" name="date">
                    <FormItem>
                        <FormLabel>Date d'obtention</FormLabel>
                        <FormControl>
                            <Input type="date" v-bind="componentField" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <!-- Lien vers la certification -->
                <FormField v-slot="{ componentField }" name="link">
                    <FormItem>
                        <FormLabel>Lien vers la certification</FormLabel>
                        <FormControl>
                            <Input type="url" placeholder="https://..." v-bind="componentField" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <!-- Sélection d'image -->
                <FormField v-slot="{ componentField }" name="picture_id">
                    <FormItem>
                        <FormLabel>Image de la certification</FormLabel>
                        <FormControl>
                            <PictureInput v-bind="componentField" :model-value="props.certification?.picture_id ?? undefined" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <!-- Boutons d'action -->
                <div class="flex justify-end space-x-4">
                    <Button type="button" variant="outline" @click="router.visit(route('dashboard.certifications.index'))"> Annuler </Button>
                    <Button type="submit" :disabled="isSubmitting">
                        <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                        {{ isEditing ? 'Mettre à jour' : 'Créer' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>