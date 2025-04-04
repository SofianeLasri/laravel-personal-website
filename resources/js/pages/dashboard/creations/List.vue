<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import {
    Pagination,
    PaginationEllipsis,
    PaginationFirst,
    PaginationLast,
    PaginationList,
    PaginationListItem,
    PaginationNext,
    PaginationPrev,
} from '@/components/ui/pagination';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';
import { ArrowDown, ArrowUp, Edit, Eye, Link as LinkIcon, MoreHorizontal, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

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

const itemsPerPage = 25;
const currentPage = ref(1);

type SortColumn = 'id' | 'name' | 'type' | 'started_at' | 'ended_at' | 'featured';
type SortDirection = 'asc' | 'desc';

const sortColumn = ref<SortColumn>('id');
const sortDirection = ref<SortDirection>('asc');

const toggleSort = (column: SortColumn) => {
    if (sortColumn.value === column) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn.value = column;
        sortDirection.value = 'asc';
    }
};

const formatDate = (dateString: string) => {
    try {
        return format(new Date(dateString), 'dd MMMM yyyy', { locale: fr });
    } catch (e) {
        console.error('Erreur de formatage de la date:', e);
        return 'Date invalide';
    }
};

const getFrenchDescription = (translationKey: TranslationKey): string => {
    const frTranslation = translationKey.translations.find((t) => t.locale === 'fr');
    return frTranslation ? frTranslation.text : '';
};

const creationTypeLabels = {
    portfolio: 'Portfolio',
    game: 'Jeu vidéo',
    library: 'Bibliothèque',
    website: 'Site web',
    tool: 'Outil',
    map: 'Map',
    other: 'Autre',
};

const getTypeLabel = (type: CreationType) => {
    return creationTypeLabels[type] || type;
};

const compareValues = (a: any, b: any, direction: SortDirection) => {
    const multiplier = direction === 'asc' ? 1 : -1;

    if (a === null || a === undefined) return multiplier;
    if (b === null || b === undefined) return -multiplier;

    if (typeof a === 'string' && typeof b === 'string') {
        return multiplier * a.localeCompare(b, 'fr', { sensitivity: 'base' });
    }

    if (typeof a === 'number' && typeof b === 'number') {
        return multiplier * (a - b);
    }

    if (typeof a === 'boolean' && typeof b === 'boolean') {
        return multiplier * (a === b ? 0 : a ? -1 : 1);
    }

    if (a instanceof Date && b instanceof Date) {
        return multiplier * (a.getTime() - b.getTime());
    }

    if (typeof a === 'string' && !isNaN(Date.parse(a)) && typeof b === 'string' && !isNaN(Date.parse(b))) {
        return multiplier * (new Date(a).getTime() - new Date(b).getTime());
    }

    return multiplier * String(a).localeCompare(String(b));
};

const sortedCreations = computed(() => {
    return [...props.creations].sort((a, b) => {
        if (sortColumn.value === 'type') {
            return compareValues(getTypeLabel(a.type), getTypeLabel(b.type), sortDirection.value);
        } else if (sortColumn.value === 'id' || sortColumn.value === 'featured') {
            return compareValues(a[sortColumn.value], b[sortColumn.value], sortDirection.value);
        } else if (sortColumn.value === 'name') {
            return compareValues(a.name, b.name, sortDirection.value);
        } else if (sortColumn.value === 'started_at' || sortColumn.value === 'ended_at') {
            return compareValues(a[sortColumn.value], b[sortColumn.value], sortDirection.value);
        }

        return 0;
    });
});

const paginatedCreations = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return sortedCreations.value.slice(start, end);
});

const handlePageChange = (page: number) => {
    currentPage.value = page;
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
                            <TableHead class="w-[100px] cursor-pointer" @click="toggleSort('id')">
                                <div class="flex items-center">
                                    ID
                                    <ArrowUp v-if="sortColumn === 'id' && sortDirection === 'asc'" class="ml-1 h-4 w-4" />
                                    <ArrowDown v-if="sortColumn === 'id' && sortDirection === 'desc'" class="ml-1 h-4 w-4" />
                                </div>
                            </TableHead>
                            <TableHead class="cursor-pointer" @click="toggleSort('name')">
                                <div class="flex items-center">
                                    Nom
                                    <ArrowUp v-if="sortColumn === 'name' && sortDirection === 'asc'" class="ml-1 h-4 w-4" />
                                    <ArrowDown v-if="sortColumn === 'name' && sortDirection === 'desc'" class="ml-1 h-4 w-4" />
                                </div>
                            </TableHead>
                            <TableHead class="cursor-pointer" @click="toggleSort('type')">
                                <div class="flex items-center">
                                    Type
                                    <ArrowUp v-if="sortColumn === 'type' && sortDirection === 'asc'" class="ml-1 h-4 w-4" />
                                    <ArrowDown v-if="sortColumn === 'type' && sortDirection === 'desc'" class="ml-1 h-4 w-4" />
                                </div>
                            </TableHead>
                            <TableHead class="cursor-pointer" @click="toggleSort('started_at')">
                                <div class="flex items-center">
                                    Date de début
                                    <ArrowUp v-if="sortColumn === 'started_at' && sortDirection === 'asc'" class="ml-1 h-4 w-4" />
                                    <ArrowDown v-if="sortColumn === 'started_at' && sortDirection === 'desc'" class="ml-1 h-4 w-4" />
                                </div>
                            </TableHead>
                            <TableHead class="cursor-pointer" @click="toggleSort('ended_at')">
                                <div class="flex items-center">
                                    Date de fin
                                    <ArrowUp v-if="sortColumn === 'ended_at' && sortDirection === 'asc'" class="ml-1 h-4 w-4" />
                                    <ArrowDown v-if="sortColumn === 'ended_at' && sortDirection === 'desc'" class="ml-1 h-4 w-4" />
                                </div>
                            </TableHead>
                            <TableHead>Description</TableHead>
                            <TableHead class="cursor-pointer" @click="toggleSort('featured')">
                                <div class="flex items-center">
                                    Mis en avant
                                    <ArrowUp v-if="sortColumn === 'featured' && sortDirection === 'asc'" class="ml-1 h-4 w-4" />
                                    <ArrowDown v-if="sortColumn === 'featured' && sortDirection === 'desc'" class="ml-1 h-4 w-4" />
                                </div>
                            </TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="creation in paginatedCreations" :key="creation.id">
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

                <!-- Pagination -->
                <div class="mt-6 flex justify-center">
                    <Pagination
                        :total="props.creations.length"
                        :items-per-page="itemsPerPage"
                        :default-page="1"
                        show-edges
                        :sibling-count="1"
                        @update:page="handlePageChange"
                        v-slot="{ page }"
                    >
                        <PaginationList v-slot="{ items }" class="flex items-center gap-1">
                            <PaginationFirst />
                            <PaginationPrev />

                            <template v-for="(item, index) in items">
                                <PaginationListItem v-if="item.type === 'page'" :key="index" :value="item.value" as-child>
                                    <Button class="h-10 w-10 p-0" :variant="item.value === page ? 'default' : 'outline'">
                                        {{ item.value }}
                                    </Button>
                                </PaginationListItem>
                                <PaginationEllipsis v-else :key="item.type" :index="index" />
                            </template>

                            <PaginationNext />
                            <PaginationLast />
                        </PaginationList>
                    </Pagination>
                </div>

                <!-- Information sur le nombre d'éléments -->
                <div class="mt-2 text-center text-sm text-muted-foreground">
                    Affichage de {{ (currentPage - 1) * itemsPerPage + 1 }} à
                    {{ Math.min(currentPage * itemsPerPage, props.creations.length) }}
                    sur {{ props.creations.length }} créations
                </div>
            </div>
        </div>
    </AppLayout>
</template>
