<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';
import { Edit, Eye, Link as LinkIcon, MoreHorizontal, Trash2 } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Créations',
        href: '#',
    },
    {
        title: 'Liste des créations',
        href: route('dashboard.creations.index', undefined, false),
    },
];

interface Translation {
    id: number;
    translation_key_id: number;
    locale: string;
    text: string;
}

interface TranslationKey {
    id: number;
    key: string;
    translations: Translation[];
}

type CreationType = 'portfolio' | 'game' | 'library' | 'website' | 'tool' | 'map' | 'other';

interface CreationWithTranslations {
    id: number;
    name: string;
    slug: string;
    logo_id: number;
    cover_image_id: number;
    type: CreationType;
    started_at: string;
    ended_at: string | null;
    short_description_translation_key_id: number;
    full_description_translation_key_id: number;
    external_url: string | null;
    source_code_url: string | null;
    featured: boolean;
    created_at: string;
    updated_at: string;
    short_description_translation_key: TranslationKey;
    full_description_translation_key: TranslationKey;
}

interface Props {
    creations: CreationWithTranslations[];
}

const props = defineProps<Props>();

// Fonction pour formatter les dates
const formatDate = (dateString: string) => {
    try {
        return format(new Date(dateString), 'dd MMMM yyyy', { locale: fr });
    } catch (e) {
        console.error('Erreur de formatage de la date:', e);
        return 'Date invalide';
    }
};

// Fonction pour obtenir la traduction française de la description
const getFrenchDescription = (translationKey: TranslationKey): string => {
    const frTranslation = translationKey.translations.find((t) => t.locale === 'fr');
    return frTranslation ? frTranslation.text : '';
};

// Mapping des types de création pour l'affichage
const creationTypeLabels = {
    portfolio: 'Portfolio',
    game: 'Jeu',
    library: 'Bibliothèque',
    website: 'Site web',
    tool: 'Outil',
    map: 'Carte',
    other: 'Autre',
};

// Obtention du label du type
const getTypeLabel = (type: CreationType) => {
    return creationTypeLabels[type] || type;
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Liste des créations" />
        <div class="px-5 py-6">
            <Heading title="Liste des créations" description="Sont affichées ici uniquement les créations publiées." />

            <div class="py-4">
                <Table>
                    <TableCaption>Liste des créations publiées</TableCaption>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-[100px]">ID</TableHead>
                            <TableHead>Nom</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead>Date de début</TableHead>
                            <TableHead>Date de fin</TableHead>
                            <TableHead>Description</TableHead>
                            <TableHead>Mis en avant</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="creation in props.creations" :key="creation.id">
                            <TableCell class="font-medium">{{ creation.id }}</TableCell>
                            <TableCell>{{ creation.name }}</TableCell>
                            <TableCell>
                                <Badge variant="outline">{{ getTypeLabel(creation.type) }}</Badge>
                            </TableCell>
                            <TableCell>{{ formatDate(creation.started_at) }}</TableCell>
                            <TableCell>{{ creation.ended_at ? formatDate(creation.ended_at) : 'En cours' }}</TableCell>
                            <TableCell class="max-w-[300px] truncate">
                                {{ getFrenchDescription(creation.short_description_translation_key) }}
                            </TableCell>
                            <TableCell>
                                <Badge :variant="creation.featured ? 'default' : 'outline'">
                                    {{ creation.featured ? 'Oui' : 'Non' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="ghost" class="h-8 w-8 p-0">
                                            <span class="sr-only">Ouvrir menu</span>
                                            <MoreHorizontal class="h-4 w-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                        <DropdownMenuItem
                                            @click="
                                                () => {
                                                    // Rediriger vers la page de détail
                                                }
                                            "
                                        >
                                            <Eye class="mr-2 h-4 w-4" />
                                            <span>Voir</span>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            @click="
                                                () => {
                                                    // Rediriger vers la page d'édition
                                                }
                                            "
                                        >
                                            <Edit class="mr-2 h-4 w-4" />
                                            <span>Modifier</span>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="creation.external_url"
                                            @click="
                                                () => {
                                                    // Rediriger vers l'URL externe
                                                }
                                            "
                                        >
                                            <LinkIcon class="mr-2 h-4 w-4" />
                                            <span>Visiter</span>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            class="text-destructive"
                                            @click="
                                                () => {
                                                    // Action de suppression
                                                }
                                            "
                                        >
                                            <Trash2 class="mr-2 h-4 w-4" />
                                            <span>Supprimer</span>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
    </AppLayout>
</template>
