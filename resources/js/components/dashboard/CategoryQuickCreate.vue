<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { useRoute } from '@/composables/useRoute';
import type { BlogCategory } from '@/types';
import { toTypedSchema } from '@vee-validate/zod';
import axios from 'axios';
import { Plus } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import * as z from 'zod';

interface Props {
    locale: 'fr' | 'en';
}

const props = defineProps<Props>();
const emit = defineEmits<{
    categoryCreated: [category: BlogCategory];
}>();

const route = useRoute();
const open = ref(false);
const isSubmitting = ref(false);

const predefinedColors = [
    '#EF4444', // red
    '#F97316', // orange
    '#EAB308', // yellow
    '#22C55E', // green
    '#06B6D4', // cyan
    '#3B82F6', // blue
    '#8B5CF6', // violet
    '#EC4899', // pink
    '#6B7280', // gray
    '#10B981', // emerald
];

const formSchema = toTypedSchema(
    z.object({
        slug: z.string().min(1, 'Le slug est requis'),
        name_fr: z.string().min(1, 'Le nom en français est requis'),
        name_en: z.string().min(1, 'Le nom en anglais est requis'),
        color: z.string().min(1, 'La couleur est requise'),
    }),
);

const form = useForm({
    validationSchema: formSchema,
    initialValues: {
        slug: '',
        name_fr: '',
        name_en: '',
        color: predefinedColors[0],
    },
});

// Auto-generate slug from French name
const generateSlug = (name: string) => {
    return name
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '') // Remove accents
        .replace(/[^a-z0-9\s-]/g, '') // Remove special chars
        .replace(/\s+/g, '-') // Replace spaces with hyphens
        .replace(/-+/g, '-') // Replace multiple hyphens
        .trim();
};

const handleNameFrChange = (value: string) => {
    form.setFieldValue('name_fr', value);
    if (!form.values.slug) {
        form.setFieldValue('slug', generateSlug(value));
    }
};

const handleSubmit = form.handleSubmit(async (values) => {
    isSubmitting.value = true;

    try {
        const response = await axios.post(route('dashboard.api.blog-categories.store'), values);

        toast.success('Catégorie créée avec succès');
        emit('categoryCreated', response.data);

        // Reset form and close dialog
        form.resetForm();
        open.value = false;
    } catch (error) {
        console.error('Erreur lors de la création:', error);
        toast.error('Erreur lors de la création de la catégorie');
    } finally {
        isSubmitting.value = false;
    }
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button type="button" variant="outline" size="sm">
                <Plus class="mr-2 h-4 w-4" />
                Nouvelle catégorie
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>Créer une nouvelle catégorie</DialogTitle>
                <DialogDescription> Ajoutez une nouvelle catégorie pour organiser vos articles. </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit="handleSubmit">
                <FormField v-slot="{ componentField }" name="name_fr">
                    <FormItem>
                        <FormLabel>Nom (Français)</FormLabel>
                        <FormControl>
                            <Input
                                type="text"
                                placeholder="Ex: Technologie"
                                v-bind="componentField"
                                @input="(e: any) => handleNameFrChange(e.target.value)"
                            />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <FormField v-slot="{ componentField }" name="name_en">
                    <FormItem>
                        <FormLabel>Nom (Anglais)</FormLabel>
                        <FormControl>
                            <Input type="text" placeholder="Ex: Technology" v-bind="componentField" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <FormField v-slot="{ componentField }" name="slug">
                    <FormItem>
                        <FormLabel>Slug</FormLabel>
                        <FormControl>
                            <Input type="text" placeholder="technologie" v-bind="componentField" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <FormField v-slot="{ componentField }" name="color">
                    <FormItem>
                        <FormLabel>Couleur</FormLabel>
                        <FormControl>
                            <div class="space-y-2">
                                <div class="grid grid-cols-5 gap-2">
                                    <button
                                        v-for="color in predefinedColors"
                                        :key="color"
                                        type="button"
                                        class="h-8 w-8 rounded-md border-2 transition-all"
                                        :class="[form.values.color === color ? 'border-foreground scale-110' : 'border-transparent hover:scale-105']"
                                        :style="{ backgroundColor: color }"
                                        @click="form.setFieldValue('color', color)"
                                    />
                                </div>
                                <Input type="color" v-bind="componentField" class="h-8 w-16" />
                            </div>
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false"> Annuler </Button>
                    <Button type="submit" :disabled="isSubmitting">
                        {{ isSubmitting ? 'Création...' : 'Créer' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>