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
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Edit, ExternalLink, Link as LinkIcon, Loader2, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

// Types
interface SocialMediaLink {
    id: number;
    name: string;
    url: string;
    icon_svg: string;
}

// Props
const props = defineProps<{
    socialMediaLinks: SocialMediaLink[];
}>();

// État local
const links = ref<SocialMediaLink[]>(props.socialMediaLinks);
const isLoading = ref(false);
const isDialogOpen = ref(false);
const isDeleteDialogOpen = ref(false);
const currentLink = ref<SocialMediaLink | null>(null);
const newLink = ref<Partial<SocialMediaLink>>({
    name: '',
    url: '',
    icon_svg: '',
});

// Breadcrumbs
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Réseaux sociaux',
        href: route('dashboard.social-media-links.index', undefined, false),
    },
];

// Méthodes
const openAddDialog = () => {
    currentLink.value = null;
    newLink.value = {
        name: '',
        url: '',
        icon_svg: '',
    };
    isDialogOpen.value = true;
};

const openEditDialog = (link: SocialMediaLink) => {
    currentLink.value = link;
    newLink.value = {
        name: link.name,
        url: link.url,
        icon_svg: link.icon_svg,
    };
    isDialogOpen.value = true;
};

const confirmDelete = (link: SocialMediaLink) => {
    currentLink.value = link;
    isDeleteDialogOpen.value = true;
};

const saveLink = async () => {
    if (!newLink.value.name || !newLink.value.url || !newLink.value.icon_svg) {
        toast.error('Tous les champs sont requis');
        return;
    }

    isLoading.value = true;

    try {
        if (currentLink.value) {
            // Mise à jour
            const response = await axios.put(
                route('dashboard.api.social-media-links.update', { social_media_link: currentLink.value.id }),
                newLink.value,
            );

            // Mettre à jour dans le tableau local
            const index = links.value.findIndex((link) => link.id === currentLink.value!.id);
            if (index !== -1) {
                links.value[index] = response.data;
            }

            toast.success('Lien mis à jour avec succès');
        } else {
            // Création
            const response = await axios.post(route('dashboard.api.social-media-links.store'), newLink.value);
            links.value.push(response.data);
            toast.success('Lien créé avec succès');
        }

        isDialogOpen.value = false;
    } catch (error) {
        console.error('Erreur lors de la sauvegarde:', error);

        let errorMessage = 'Une erreur est survenue';
        if (axios.isAxiosError(error) && error.response) {
            if (error.response.status === 422) {
                errorMessage = 'Veuillez vérifier les données saisies';
            } else {
                errorMessage = `Erreur ${error.response.status}: ${error.response.statusText}`;
            }
        }

        toast.error(errorMessage);
    } finally {
        isLoading.value = false;
    }
};

const deleteLink = async () => {
    if (!currentLink.value) return;

    isLoading.value = true;

    try {
        await axios.delete(route('dashboard.api.social-media-links.destroy', { social_media_link: currentLink.value.id }));

        // Supprimer du tableau local
        links.value = links.value.filter((link) => link.id !== currentLink.value!.id);

        toast.success('Lien supprimé avec succès');
        isDeleteDialogOpen.value = false;
    } catch (error) {
        console.error('Erreur lors de la suppression:', error);
        toast.error('Erreur lors de la suppression du lien');
    } finally {
        isLoading.value = false;
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Réseaux sociaux" />

        <div class="px-5 py-6">
            <div class="flex items-center justify-between">
                <Heading title="Réseaux sociaux" description="Gérez vos liens vers les réseaux sociaux et plateformes en ligne." />

                <Button type="button" @click="openAddDialog">
                    <Plus class="mr-2 h-4 w-4" />
                    Nouveau lien
                </Button>
            </div>

            <div class="mt-6">
                <Table v-if="links.length > 0">
                    <TableCaption>Liste de vos liens sociaux</TableCaption>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-[50px]">Icône</TableHead>
                            <TableHead>Nom</TableHead>
                            <TableHead>URL</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="link in links" :key="link.id">
                            <TableCell>
                                <div class="h-8 w-8" v-html="link.icon_svg"></div>
                            </TableCell>
                            <TableCell class="font-medium">{{ link.name }}</TableCell>
                            <TableCell class="max-w-xs truncate">{{ link.url }}</TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end space-x-2">
                                    <Button variant="ghost" size="icon" type="button" @click="openEditDialog(link)" title="Modifier">
                                        <Edit class="h-4 w-4" />
                                    </Button>
                                    <Button variant="ghost" size="icon" as-child title="Visiter">
                                        <a :href="link.url" target="_blank" rel="noopener noreferrer">
                                            <ExternalLink class="h-4 w-4" />
                                        </a>
                                    </Button>
                                    <Button variant="ghost" size="icon" type="button" @click="confirmDelete(link)" title="Supprimer">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <div v-else class="border-border text-muted-foreground rounded-lg border p-8 text-center">
                    <LinkIcon class="mx-auto mb-4 h-12 w-12" />
                    <p class="mb-4">Aucun lien social n'a été ajouté.</p>
                    <Button type="button" @click="openAddDialog">
                        <Plus class="mr-2 h-4 w-4" />
                        Ajouter un lien
                    </Button>
                </div>
            </div>
        </div>

        <!-- Dialog pour ajouter/modifier un lien -->
        <Dialog v-model:open="isDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ currentLink ? 'Modifier un lien' : 'Ajouter un lien' }}</DialogTitle>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Nom</label>
                        <Input v-model="newLink.name" placeholder="ex: LinkedIn, Twitter, etc." />
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">URL</label>
                        <Input v-model="newLink.url" placeholder="https://..." />
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Icône SVG</label>
                        <Textarea v-model="newLink.icon_svg" placeholder="<svg>...</svg>" rows="4" />

                        <div v-if="newLink.icon_svg" class="mt-2 flex justify-center rounded-md border p-2">
                            <div class="h-10 w-10" v-html="newLink.icon_svg"></div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" type="button" @click="isDialogOpen = false" :disabled="isLoading"> Annuler </Button>
                    <Button type="button" @click="saveLink" :disabled="isLoading">
                        <Loader2 v-if="isLoading" class="mr-2 h-4 w-4 animate-spin" />
                        {{ currentLink ? 'Mettre à jour' : 'Ajouter' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Dialog de confirmation de suppression -->
        <AlertDialog v-model:open="isDeleteDialogOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Confirmer la suppression</AlertDialogTitle>
                    <AlertDialogDescription> Êtes-vous sûr de vouloir supprimer ce lien ? Cette action est irréversible. </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="isDeleteDialogOpen = false" :disabled="isLoading"> Annuler </AlertDialogCancel>
                    <AlertDialogAction
                        @click="deleteLink"
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
