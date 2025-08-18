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
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { useRoute } from '@/composables/useRoute';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';
import { Briefcase, Edit, ExternalLink, GraduationCap, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

// Types pour notre page
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
    technologies: Array<{
        id: number;
        name: string;
        svg_icon: string;
    }>;
}

interface Props {
    experiences: Experience[];
}

// Configuration de la page
const props = defineProps<Props>();
const route = useRoute();
const locale = ref<'fr' | 'en'>('fr');
const deleteConfirmationOpen = ref(false);
const experienceToDelete = ref<Experience | null>(null);

// Breadcrumbs pour la navigation
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Expériences',
        href: route('dashboard.experiences.index', undefined, false),
    },
];

// Fonctions utilitaires
const formatDate = (dateString: string) => {
    return format(new Date(dateString), 'MMMM yyyy', { locale: fr });
};

const getTranslation = (translationKey: any, locale: string) => {
    if (!translationKey || !translationKey.translations) return '';
    const translation = translationKey.translations.find((t: any) => t.locale === locale);
    return translation ? translation.text : '';
};

// Séparer les expériences par type
const employmentExperiences = computed(() => {
    return props.experiences.filter((exp) => exp.type === 'emploi');
});

const educationExperiences = computed(() => {
    return props.experiences.filter((exp) => exp.type === 'formation');
});

// Fonction pour confirmer la suppression
const confirmDelete = (experience: Experience) => {
    experienceToDelete.value = experience;
    deleteConfirmationOpen.value = true;
};

// Fonction pour effectuer la suppression
const deleteExperience = async () => {
    if (!experienceToDelete.value) return;

    try {
        await axios.delete(route('dashboard.api.experiences.destroy', { experience: experienceToDelete.value.id }));
        toast.success('Expérience supprimée avec succès');
        if (typeof window !== 'undefined') {
            window.location.reload();
        }
    } catch (error) {
        console.error('Erreur lors de la suppression :', error);
        toast.error("Erreur lors de la suppression de l'expérience");
    } finally {
        deleteConfirmationOpen.value = false;
        experienceToDelete.value = null;
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Expériences" />

        <div class="px-5 py-6">
            <div class="flex items-center justify-between">
                <Heading title="Expériences" description="Gérez vos expériences professionnelles et éducatives." />

                <div class="flex space-x-2">
                    <Button as-child>
                        <Link :href="route('dashboard.experiences.create')">
                            <Plus class="mr-2 h-4 w-4" />
                            Nouvelle expérience
                        </Link>
                    </Button>

                    <div class="flex items-center space-x-2">
                        <Button variant="outline" size="sm" :class="{ 'bg-primary text-primary-foreground': locale === 'fr' }" @click="locale = 'fr'">
                            FR
                        </Button>
                        <Button variant="outline" size="sm" :class="{ 'bg-primary text-primary-foreground': locale === 'en' }" @click="locale = 'en'">
                            EN
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Expériences professionnelles -->
            <div class="mt-8">
                <h2 class="flex items-center text-xl font-semibold">
                    <Briefcase class="mr-2 h-5 w-5" />
                    Expériences professionnelles
                </h2>

                <div v-if="employmentExperiences.length === 0" class="border-border mt-4 rounded-lg border p-8 text-center">
                    <p class="text-muted-foreground mb-4">Aucune expérience professionnelle n'a été ajoutée.</p>
                    <Button as-child variant="outline">
                        <Link :href="route('dashboard.experiences.create')">
                            <Plus class="mr-2 h-4 w-4" />
                            Ajouter une expérience professionnelle
                        </Link>
                    </Button>
                </div>

                <div v-else class="mt-4 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <Card v-for="experience in employmentExperiences" :key="experience.id" class="overflow-hidden">
                        <CardHeader class="relative pb-0">
                            <div class="absolute top-4 right-4 flex space-x-1">
                                <Button variant="ghost" size="icon" as-child>
                                    <Link :href="route('dashboard.experiences.edit', experience.id)">
                                        <Edit class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button variant="ghost" size="icon" @click="confirmDelete(experience)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>

                            <div class="flex items-center">
                                <div v-if="experience.logo" class="mr-4 h-12 w-12 flex-shrink-0">
                                    <img
                                        :src="`/storage/${experience.logo.path_original}`"
                                        :alt="experience.organization_name"
                                        class="h-full w-full object-contain"
                                    />
                                </div>

                                <div>
                                    <CardTitle>{{ getTranslation(experience.title_translation_key, locale) }}</CardTitle>
                                    <CardDescription>{{ experience.organization_name }}</CardDescription>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent class="mt-4">
                            <div class="mb-3 flex items-center justify-between text-sm">
                                <div>
                                    <span>{{ formatDate(experience.started_at) }}</span>
                                    <span> - </span>
                                    <span v-if="experience.ended_at">{{ formatDate(experience.ended_at) }}</span>
                                    <span v-else>Présent</span>
                                </div>
                                <div class="text-muted-foreground">{{ experience.location }}</div>
                            </div>

                            <p class="text-sm">{{ getTranslation(experience.short_description_translation_key, locale) }}</p>

                            <div v-if="experience.technologies.length > 0" class="mt-4">
                                <div class="flex flex-wrap gap-2">
                                    <div
                                        v-for="tech in experience.technologies"
                                        :key="tech.id"
                                        class="bg-muted/70 flex items-center rounded-full px-2 py-1 text-xs"
                                    >
                                        <span class="mr-1 h-3 w-3" v-html="tech.svg_icon"></span>
                                        <span>{{ tech.name }}</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>

                        <CardFooter v-if="experience.website_url" class="border-t pt-4">
                            <Button variant="outline" size="sm" class="w-full" as-child>
                                <a :href="experience.website_url" target="_blank" rel="noopener noreferrer">
                                    <ExternalLink class="mr-2 h-3 w-3" />
                                    Visiter le site
                                </a>
                            </Button>
                        </CardFooter>
                    </Card>
                </div>
            </div>

            <!-- Formations -->
            <div class="mt-12">
                <h2 class="flex items-center text-xl font-semibold">
                    <GraduationCap class="mr-2 h-5 w-5" />
                    Formations
                </h2>

                <div v-if="educationExperiences.length === 0" class="border-border mt-4 rounded-lg border p-8 text-center">
                    <p class="text-muted-foreground mb-4">Aucune formation n'a été ajoutée.</p>
                    <Button as-child variant="outline">
                        <Link :href="route('dashboard.experiences.create')">
                            <Plus class="mr-2 h-4 w-4" />
                            Ajouter une formation
                        </Link>
                    </Button>
                </div>

                <div v-else class="mt-4 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <Card v-for="experience in educationExperiences" :key="experience.id" class="overflow-hidden">
                        <CardHeader class="relative pb-0">
                            <div class="absolute top-4 right-4 flex space-x-1">
                                <Button variant="ghost" size="icon" as-child>
                                    <Link :href="route('dashboard.experiences.edit', experience.id)">
                                        <Edit class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button variant="ghost" size="icon" @click="confirmDelete(experience)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>

                            <div class="flex items-center">
                                <div v-if="experience.logo" class="mr-4 h-12 w-12 flex-shrink-0">
                                    <img
                                        :src="`/storage/${experience.logo.path_original}`"
                                        :alt="experience.organization_name"
                                        class="h-full w-full object-contain"
                                    />
                                </div>

                                <div>
                                    <CardTitle>{{ getTranslation(experience.title_translation_key, locale) }}</CardTitle>
                                    <CardDescription>{{ experience.organization_name }}</CardDescription>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent class="mt-4">
                            <div class="mb-3 flex items-center justify-between text-sm">
                                <div>
                                    <span>{{ formatDate(experience.started_at) }}</span>
                                    <span> - </span>
                                    <span v-if="experience.ended_at">{{ formatDate(experience.ended_at) }}</span>
                                    <span v-else>Présent</span>
                                </div>
                                <div class="text-muted-foreground">{{ experience.location }}</div>
                            </div>

                            <p class="text-sm">{{ getTranslation(experience.short_description_translation_key, locale) }}</p>

                            <div v-if="experience.technologies.length > 0" class="mt-4">
                                <div class="flex flex-wrap gap-2">
                                    <div
                                        v-for="tech in experience.technologies"
                                        :key="tech.id"
                                        class="bg-muted/70 flex items-center rounded-full px-2 py-1 text-xs"
                                    >
                                        <span class="mr-1 h-3 w-3" v-html="tech.svg_icon"></span>
                                        <span>{{ tech.name }}</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>

                        <CardFooter v-if="experience.website_url" class="border-t pt-4">
                            <Button variant="outline" size="sm" class="w-full" as-child>
                                <a :href="experience.website_url" target="_blank" rel="noopener noreferrer">
                                    <ExternalLink class="mr-2 h-3 w-3" />
                                    Visiter le site
                                </a>
                            </Button>
                        </CardFooter>
                    </Card>
                </div>
            </div>
        </div>

        <!-- Dialogue de confirmation de suppression -->
        <AlertDialog v-model:open="deleteConfirmationOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Confirmer la suppression</AlertDialogTitle>
                    <AlertDialogDescription>
                        Êtes-vous sûr de vouloir supprimer cette expérience ? Cette action est irréversible.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="deleteConfirmationOpen = false"> Annuler </AlertDialogCancel>
                    <AlertDialogAction @click="deleteExperience" class="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                        Supprimer
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
