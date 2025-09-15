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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationFirst,
    PaginationItem,
    PaginationLast,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useRoute } from '@/composables/useRoute';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BlogPostWithAllRelations, BreadcrumbItem, TranslationKey } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';
import { ArrowDown, ArrowUp, Edit, MoreHorizontal, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    blogPosts: BlogPostWithAllRelations[];
}

const props = defineProps<Props>();
const route = useRoute();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Articles',
        href: '#',
    },
    {
        title: 'Liste des articles',
        href: route('dashboard.blog-posts.index', undefined, false),
    },
];

const showDraftAlert = ref(false);
const selectedPost = ref<BlogPostWithAllRelations | null>(null);

const itemsPerPage = 25;
const currentPage = ref(1);

type SortColumn = 'id' | 'type' | 'status' | 'published_at';
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

const getFrenchTranslation = (translationKey: TranslationKey): string => {
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

const sortedPosts = computed(() => {
    return [...props.blogPosts].sort((a, b) => {
        if (sortColumn.value === 'id') {
            return compareValues(a[sortColumn.value], b[sortColumn.value], sortDirection.value);
        } else if (sortColumn.value === 'type') {
            return compareValues(a.type, b.type, sortDirection.value);
        } else if (sortColumn.value === 'status') {
            return compareValues(a.type, b.type, sortDirection.value);
        } else if (sortColumn.value === 'published_at' || sortColumn.value === 'published_at') {
            return compareValues(a[sortColumn.value], b[sortColumn.value], sortDirection.value);
        }

        return 0;
    });
});

const paginatedPosts = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return sortedPosts.value.slice(start, end);
});

const handlePageChange = (page: number) => {
    currentPage.value = page;
};

const deletePost = async (id: number) => {
    try {
        await axios.delete(route('dashboard.api.creations.destroy', { creation: id }));
        router.reload();
    } catch (error) {
        console.error('Erreur lors de la suppression:', error);
    }
};

const handleEditCreation = (post: BlogPostWithAllRelations) => {
    if (post.drafts && post.drafts.length > 0) {
        selectedPost.value = post;
        showDraftAlert.value = true;
    } else {
        router.visit(route('dashboard.creations.edit', { 'creation-id': post.id })); // TODO: Use correct route
    }
};

const navigateToDraftEdit = () => {
    if (selectedPost.value && selectedPost.value.drafts.length > 0) {
        const draftId = selectedPost.value.drafts[0].id;
        router.visit(route('dashboard.creations.edit', { 'draft-id': draftId })); // TODO: Use correct route
    }
    showDraftAlert.value = false;
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Liste des articles" />
        <div class="px-5 py-6">
            <Heading title="Liste des articles" description="Sont affichées ici uniquement les créations publiées." />

            <div class="py-4">
                <Table>
                    <TableCaption>Liste des articles publiés</TableCaption>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-[100px] cursor-pointer" @click="toggleSort('id')">
                                <div class="flex items-center">
                                    ID
                                    <ArrowUp v-if="sortColumn === 'id' && sortDirection === 'asc'" class="ml-1 h-4 w-4" />
                                    <ArrowDown v-if="sortColumn === 'id' && sortDirection === 'desc'" class="ml-1 h-4 w-4" />
                                </div>
                            </TableHead>
                            <TableHead class="text-right">Titre</TableHead>
                            <TableHead class="cursor-pointer" @click="toggleSort('type')">
                                <div class="flex items-center">
                                    Type
                                    <ArrowUp v-if="sortColumn === 'type' && sortDirection === 'asc'" class="ml-1 h-4 w-4" />
                                    <ArrowDown v-if="sortColumn === 'type' && sortDirection === 'desc'" class="ml-1 h-4 w-4" />
                                </div>
                            </TableHead>
                            <TableHead>Description</TableHead>
                            <TableHead class="cursor-pointer" @click="toggleSort('published_at')">
                                <div class="flex items-center">
                                    Publié le
                                    <ArrowUp v-if="sortColumn === 'published_at' && sortDirection === 'asc'" class="ml-1 h-4 w-4" />
                                    <ArrowDown v-if="sortColumn === 'published_at' && sortDirection === 'desc'" class="ml-1 h-4 w-4" />
                                </div>
                            </TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="post in paginatedPosts" :key="post.id">
                            <TableCell class="font-medium">{{ post.id }}</TableCell>
                            <TableCell>{{ getFrenchTranslation(post.title_translation_key) }}</TableCell>
                            <TableCell>
                                <Badge variant="outline">{{ post.type }}</Badge>
                                <!--                                    <Badge variant="outline">{{ getTypeLabel(post.type) }}</Badge>-->
                            </TableCell>
                            <TableCell>{{ formatDate(post.published_at) }}</TableCell>
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
                                        <DropdownMenuItem @click="handleEditCreation(post)">
                                            <Edit class="mr-2 h-4 w-4" />
                                            <span>Modifier</span>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem class="text-destructive" @click="deletePost(post.id)">
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
                        :total="props.blogPosts.length"
                        :items-per-page="itemsPerPage"
                        :default-page="1"
                        show-edges
                        :sibling-count="1"
                        @update:page="handlePageChange"
                        v-slot="{ page }"
                    >
                        <PaginationContent v-slot="{ items }" class="flex items-center gap-1">
                            <PaginationFirst />
                            <PaginationPrevious />

                            <template v-for="(item, index) in items">
                                <PaginationItem v-if="item.type === 'page'" :key="index" :value="item.value" as-child>
                                    <Button class="h-10 w-10 p-0" :variant="item.value === page ? 'default' : 'outline'">
                                        {{ item.value }}
                                    </Button>
                                </PaginationItem>
                                <PaginationEllipsis v-else :key="item.type" :index="index" />
                            </template>

                            <PaginationNext />
                            <PaginationLast />
                        </PaginationContent>
                    </Pagination>
                </div>

                <!-- Information sur le nombre d'éléments -->
                <div class="text-muted-foreground mt-2 text-center text-sm">
                    Affichage de {{ (currentPage - 1) * itemsPerPage + 1 }} à
                    {{ Math.min(currentPage * itemsPerPage, props.blogPosts.length) }}
                    sur {{ props.blogPosts.length }} créations
                </div>
            </div>
        </div>

        <AlertDialog :open="showDraftAlert" @update:open="showDraftAlert = $event">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Brouillon existant</AlertDialogTitle>
                    <AlertDialogDescription>
                        Cet article possède déjà un brouillon. Voulez-vous continuer et modifier ce brouillon existant ?
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="showDraftAlert = false">Annuler</AlertDialogCancel>
                    <AlertDialogAction @click="navigateToDraftEdit">Modifier le brouillon</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
