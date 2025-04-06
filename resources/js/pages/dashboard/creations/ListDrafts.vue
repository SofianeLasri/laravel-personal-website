<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import { BreadcrumbItem, CreationDraftWithTranslations, TranslationKey } from '@/types';
import { getTypeLabel } from '@/utils/creationTypes';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';
import { ArrowDown, ArrowUp, Clock, Edit, Eye, MoreHorizontal, Send, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Créations',
        href: '#',
    },
    {
        title: 'Liste des brouillons',
        href: route('dashboard.creations.drafts.index', undefined, false),
    },
];

interface Props {
    creationDrafts: CreationDraftWithTranslations[];
}

const props = defineProps<Props>();

const itemsPerPage = 25;
const currentPage = ref(1);

type SortColumn = 'id' | 'name' | 'type' | 'started_at' | 'updated_at' | 'original_creation_id';
type SortDirection = 'asc' | 'desc';

const sortColumn = ref<SortColumn>('updated_at');
const sortDirection = ref<SortDirection>('desc');

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
        console.error(e);
        return 'Date invalide';
    }
};

const formatDateTime = (dateString: string) => {
    try {
        return format(new Date(dateString), 'dd/MM/yyyy à HH:mm', { locale: fr });
    } catch (e) {
        console.error(e);
        return 'Date invalide';
    }
};

const getFrenchDescription = (translationKey: TranslationKey): string => {
    const frTranslation = translationKey.translations.find((t) => t.locale === 'fr');
    return frTranslation ? frTranslation.text : '';
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

const sortedDrafts = computed(() => {
    return [...props.creationDrafts].sort((a, b) => {
        if (sortColumn.value === 'type') {
            return compareValues(getTypeLabel(a.type), getTypeLabel(b.type), sortDirection.value);
        } else if (sortColumn.value === 'id' || sortColumn.value === 'original_creation_id') {
            return compareValues(a[sortColumn.value], b[sortColumn.value], sortDirection.value);
        } else if (sortColumn.value === 'name') {
            return compareValues(a.name, b.name, sortDirection.value);
        } else if (sortColumn.value === 'started_at' || sortColumn.value === 'updated_at') {
            return compareValues(a[sortColumn.value], b[sortColumn.value], sortDirection.value);
        }

        return 0;
    });
});

const paginatedDrafts = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return sortedDrafts.value.slice(start, end);
});

const handlePageChange = (page: number) => {
    currentPage.value = page;
};

type DraftBadgeStatus = {
    label: string;
    variant: 'default' | 'secondary';
};

const getDraftStatus = (draft: CreationDraftWithTranslations): DraftBadgeStatus => {
    if (draft.original_creation_id) {
        return { label: 'Modification', variant: 'secondary' };
    }
    return { label: 'Nouveau', variant: 'default' };
};

const deleteDraft = async (id: number) => {
    try {
        await axios.delete(route('dashboard.api.creation-drafts.destroy', { creation_draft: id }));
        router.reload();
    } catch (error) {
        console.error('Erreur lors de la suppression:', error);
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Liste des brouillons" />
        <div class="px-5 py-6">
            <Heading title="Liste des brouillons" description="Sont affichées ici uniquement les brouillons des créations." />

            <div class="py-4">
                <Table>
                    <TableCaption>Liste des brouillons de créations</TableCaption>
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
                            <TableHead>Description</TableHead>
                            <TableHead class="cursor-pointer" @click="toggleSort('updated_at')">
                                <div class="flex items-center">
                                    Dernière modif.
                                    <ArrowUp v-if="sortColumn === 'updated_at' && sortDirection === 'asc'" class="ml-1 h-4 w-4" />
                                    <ArrowDown v-if="sortColumn === 'updated_at' && sortDirection === 'desc'" class="ml-1 h-4 w-4" />
                                </div>
                            </TableHead>
                            <TableHead class="cursor-pointer" @click="toggleSort('original_creation_id')">
                                <div class="flex items-center">
                                    Statut
                                    <ArrowUp v-if="sortColumn === 'original_creation_id' && sortDirection === 'asc'" class="ml-1 h-4 w-4" />
                                    <ArrowDown v-if="sortColumn === 'original_creation_id' && sortDirection === 'desc'" class="ml-1 h-4 w-4" />
                                </div>
                            </TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="draft in paginatedDrafts" :key="draft.id">
                            <TableCell class="font-medium">{{ draft.id }}</TableCell>
                            <TableCell>{{ draft.name }}</TableCell>
                            <TableCell>
                                <Badge variant="outline">{{ getTypeLabel(draft.type) }}</Badge>
                            </TableCell>
                            <TableCell>{{ formatDate(draft.started_at) }}</TableCell>
                            <TableCell class="max-w-[300px] truncate">
                                {{ getFrenchDescription(draft.short_description_translation_key) }}
                            </TableCell>
                            <TableCell class="whitespace-nowrap">
                                <div class="flex items-center">
                                    <Clock class="mr-1.5 h-3.5 w-3.5 text-muted-foreground" />
                                    {{ formatDateTime(draft.updated_at) }}
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge :variant="getDraftStatus(draft).variant">
                                    {{ getDraftStatus(draft).label }}
                                </Badge>
                                <span v-if="draft.original_creation_id" class="ml-1 text-xs text-muted-foreground">
                                    (ID: {{ draft.original_creation_id }})
                                </span>
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
                                                    // Rediriger vers la page de détail du brouillon
                                                }
                                            "
                                        >
                                            <Eye class="mr-2 h-4 w-4" />
                                            <span>Voir</span>
                                        </DropdownMenuItem>

                                        <Link :href="route('dashboard.creations.edit', { 'draft-id': draft.id })">
                                            <DropdownMenuItem>
                                                <Edit class="mr-2 h-4 w-4" />
                                                <span>Modifier</span>
                                            </DropdownMenuItem>
                                        </Link>

                                        <DropdownMenuSeparator />

                                        <DropdownMenuItem
                                            @click="
                                                () => {
                                                    // Action pour publier le brouillon
                                                }
                                            "
                                        >
                                            <Send class="mr-2 h-4 w-4" />
                                            <span>Publier</span>
                                        </DropdownMenuItem>

                                        <DropdownMenuSeparator />

                                        <DropdownMenuItem class="text-destructive" @click="deleteDraft(draft.id)">
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
                        v-if="props.creationDrafts.length > 0"
                        :total="props.creationDrafts.length"
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
                <div v-if="props.creationDrafts.length > 0" class="mt-2 text-center text-sm text-muted-foreground">
                    Affichage de {{ (currentPage - 1) * itemsPerPage + 1 }} à
                    {{ Math.min(currentPage * itemsPerPage, props.creationDrafts.length) }}
                    sur {{ props.creationDrafts.length }} brouillons
                </div>

                <div v-else class="py-10 text-center text-muted-foreground">Aucun brouillon de création pour le moment.</div>
            </div>
        </div>
    </AppLayout>
</template>
