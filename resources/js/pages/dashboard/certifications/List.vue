<script setup lang="ts">
import Heading from '@/components/dashboard/shared/ui/Heading.vue';
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
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';
import { Award, Edit, ExternalLink, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

// Types pour notre page
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

interface Props {
    certifications: Certification[];
}

// Configuration de la page
defineProps<Props>();
const deleteConfirmationOpen = ref(false);
const certificationToDelete = ref<Certification | null>(null);

// Breadcrumbs pour la navigation
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Certifications',
        href: route('dashboard.certifications.index', undefined, false),
    },
];

// Fonctions utilitaires
const formatDate = (dateString: string) => {
    return format(new Date(dateString), 'dd MMMM yyyy', { locale: fr });
};

// Fonction pour confirmer la suppression
const confirmDelete = (certification: Certification) => {
    certificationToDelete.value = certification;
    deleteConfirmationOpen.value = true;
};

// Fonction pour effectuer la suppression
const deleteCertification = async () => {
    if (!certificationToDelete.value) return;

    try {
        await axios.delete(route('dashboard.api.certifications.destroy', { certification: certificationToDelete.value.id }));
        toast.success('Certification supprimée avec succès');
        if (typeof window !== 'undefined') {
            window.location.reload();
        }
    } catch (error) {
        console.error('Erreur lors de la suppression :', error);
        toast.error('Erreur lors de la suppression de la certification');
    } finally {
        deleteConfirmationOpen.value = false;
        certificationToDelete.value = null;
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Certifications" />

        <div class="px-5 py-6">
            <div class="flex items-center justify-between">
                <Heading title="Certifications" description="Gérez vos certifications professionnelles." />

                <Button as-child>
                    <Link :href="route('dashboard.certifications.create')">
                        <Plus class="mr-2 h-4 w-4" />
                        Nouvelle certification
                    </Link>
                </Button>
            </div>

            <!-- Certifications -->
            <div class="mt-8">
                <h2 class="flex items-center text-xl font-semibold">
                    <Award class="mr-2 h-5 w-5" />
                    Mes certifications
                </h2>

                <div v-if="certifications.length === 0" class="mt-4 rounded-lg border p-8 text-center">
                    <p class="text-muted-foreground mb-4">Aucune certification n'a été ajoutée.</p>
                    <Button as-child variant="outline">
                        <Link :href="route('dashboard.certifications.create')">
                            <Plus class="mr-2 h-4 w-4" />
                            Ajouter une certification
                        </Link>
                    </Button>
                </div>

                <div v-else class="mt-4 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <Card v-for="certification in certifications" :key="certification.id" class="overflow-hidden">
                        <CardHeader class="relative pb-0">
                            <div class="absolute top-4 right-4 flex space-x-1">
                                <Button variant="ghost" size="icon" as-child>
                                    <Link :href="route('dashboard.certifications.edit', certification.id)">
                                        <Edit class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button variant="ghost" size="icon" @click="confirmDelete(certification)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>

                            <div class="flex items-center">
                                <div v-if="certification.picture" class="mr-4 h-12 w-12 flex-shrink-0">
                                    <img
                                        :src="`/storage/${certification.picture.path_original}`"
                                        :alt="certification.name"
                                        class="h-full w-full object-contain"
                                    />
                                </div>
                                <div v-else class="bg-muted mr-4 flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-lg">
                                    <Award class="text-muted-foreground h-6 w-6" />
                                </div>

                                <div>
                                    <CardTitle>{{ certification.name }}</CardTitle>
                                    <CardDescription>{{ certification.level }}</CardDescription>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent class="mt-4">
                            <div class="mb-3 text-sm">
                                <span class="text-muted-foreground">Score :</span>
                                <span class="ml-1 font-medium">{{ certification.score }}</span>
                            </div>
                            <div class="mb-3 text-sm">
                                <span class="text-muted-foreground">Obtenue le :</span>
                                <span class="ml-1 font-medium">{{ formatDate(certification.date) }}</span>
                            </div>
                        </CardContent>

                        <CardFooter class="border-t pt-4">
                            <Button variant="outline" size="sm" class="w-full" as-child>
                                <a :href="certification.link" target="_blank" rel="noopener noreferrer">
                                    <ExternalLink class="mr-2 h-3 w-3" />
                                    Voir la certification
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
                        Êtes-vous sûr de vouloir supprimer cette certification ? Cette action est irréversible.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="deleteConfirmationOpen = false"> Annuler </AlertDialogCancel>
                    <AlertDialogAction class="bg-destructive text-destructive-foreground hover:bg-destructive/90" @click="deleteCertification">
                        Supprimer
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
