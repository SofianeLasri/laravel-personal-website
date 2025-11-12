<script setup lang="ts">
import Heading from '@/components/dashboard/shared/ui/Heading.vue';
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
import type { BlogPostDraftWithAllRelations, BreadcrumbItem, TranslationKey } from '@/types';
import { compareValues, type SortDirection } from '@/utils/sorting';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';
import { ArrowDown, ArrowUp, Clock, Edit, Eye, MoreHorizontal, Send, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    blogPostDrafts: BlogPostDraftWithAllRelations[];
}

const props = defineProps<Props>();
const route = useRoute();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Articles',
        href: '#',
    },
    {
        title: 'Liste des brouillons',
        href: route('dashboard.blog-posts.drafts.index', undefined, false),
    },
];

const itemsPerPage = 25;
const currentPage = ref(1);

type SortColumn = 'id' | 'type' | 'updated_at' | 'original_blog_post_id';

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

const formatDateTime = (dateString: string) => {
    try {
        return format(new Date(dateString), 'dd/MM/yyyy à HH:mm', { locale: fr });
    } catch (e) {
        console.error(e);
        return 'Date invalide';
    }
};

const getFrenchTranslation = (translationKey: TranslationKey): string => {
    const frTranslation = translationKey.translations.find((t) => t.locale === 'fr');
    return frTranslation ? frTranslation.text : '';
};

// Function moved to @/utils/sorting

const sortedDrafts = computed(() => {
    return [...props.blogPostDrafts].sort((a, b) => {
        if (sortColumn.value === 'id' || sortColumn.value === 'original_blog_post_id') {
            return compareValues(a[sortColumn.value], b[sortColumn.value], sortDirection.value);
        } else if (sortColumn.value === 'type') {
            return compareValues(a[sortColumn.value], b[sortColumn.value], sortDirection.value);
        } else if (sortColumn.value === 'updated_at') {
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

const getDraftStatus = (draft: BlogPostDraftWithAllRelations): DraftBadgeStatus => {
    if (draft.original_blog_post_id) {
        return { label: 'Modification', variant: 'secondary' };
    }
    return { label: 'Nouveau', variant: 'default' };
};

const getTypeLabel = (type: string): string => {
    const typeLabels: Record<string, string> = {
        article: 'Article',
        tutorial: 'Tutoriel',
        news: 'Actualité',
        review: 'Critique',
        guide: 'Guide',
        game_review: 'Critique de jeu',
    };
    return typeLabels[type] || type;
};

const deleteDraft = async (id: number) => {
    try {
        await axios.delete(route('dashboard.api.blog-post-drafts.destroy', { blog_post_draft: id }));
        router.reload();
    } catch (error) {
        console.error('Erreur lors de la suppression:', error);
    }
};

const publishDraft = async (id: number) => {
    try {
        await axios.post(route('dashboard.api.blog-posts.store'), {
            draft_id: id,
        });

        toast.success('Votre article a été publié avec succès');
        router.reload();
    } catch (error) {
        console.error('Erreur lors de la publication:', error);

        let errorMessage = 'Une erreur est survenue lors de la publication';

        if (axios.isAxiosError(error) && error.response) {
            if (error.response.status === 422) {
                errorMessage = 'Le brouillon contient des erreurs qui empêchent sa publication';
            } else {
                errorMessage = `Erreur ${error.response.status}: ${error.response.statusText}`;
            }
        }

        toast.error(errorMessage);
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Liste des brouillons d'articles" />
        <div class="px-5 py-6">
            <Heading title="Liste des brouillons" description="Sont affichés ici uniquement les brouillons des articles de blog." />

            <div class="py-4">
                <Table>
                    <TableCaption>Liste des brouillons d'articles de blog</TableCaption>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-[100px] cursor-pointer" @click="toggleSort('id')">
                                <div class="flex items-center">
                                    ID
                                    <ArrowUp v-if="sortColumn === 'id' && sortDirection === 'asc'" class="ml-1 h-4 w-4" />
                                    <ArrowDown v-if="sortColumn === 'id' && sortDirection === 'desc'" class="ml-1 h-4 w-4" />
                                </div>
                            </TableHead>
                            <TableHead>Titre</TableHead>
                            <TableHead class="cursor-pointer" @click="toggleSort('type')">
                                <div class="flex items-center">
                                    Type
                                    <ArrowUp v-if="sortColumn === 'type' && sortDirection === 'asc'" class="ml-1 h-4 w-4" />
                                    <ArrowDown v-if="sortColumn === 'type' && sortDirection === 'desc'" class="ml-1 h-4 w-4" />
                                </div>
                            </TableHead>
                            <TableHead class="cursor-pointer" @click="toggleSort('updated_at')">
                                <div class="flex items-center">
                                    Dernière modif.
                                    <ArrowUp v-if="sortColumn === 'updated_at' && sortDirection === 'asc'" class="ml-1 h-4 w-4" />
                                    <ArrowDown v-if="sortColumn === 'updated_at' && sortDirection === 'desc'" class="ml-1 h-4 w-4" />
                                </div>
                            </TableHead>
                            <TableHead class="cursor-pointer" @click="toggleSort('original_blog_post_id')">
                                <div class="flex items-center">
                                    Status
                                    <ArrowUp v-if="sortColumn === 'original_blog_post_id' && sortDirection === 'asc'" class="ml-1 h-4 w-4" />
                                    <ArrowDown v-if="sortColumn === 'original_blog_post_id' && sortDirection === 'desc'" class="ml-1 h-4 w-4" />
                                </div>
                            </TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="draft in paginatedDrafts" :key="draft.id">
                            <TableCell class="font-medium">{{ draft.id }}</TableCell>
                            <TableCell>{{ getFrenchTranslation(draft.title_translation_key) }}</TableCell>
                            <TableCell>
                                <Badge variant="outline">{{ getTypeLabel(draft.type) }}</Badge>
                            </TableCell>
                            <TableCell class="whitespace-nowrap">
                                <div class="flex items-center">
                                    <Clock class="text-muted-foreground mr-1.5 h-3.5 w-3.5" />
                                    {{ formatDateTime(draft.updated_at) }}
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge :variant="getDraftStatus(draft).variant">
                                    {{ getDraftStatus(draft).label }}
                                </Badge>
                                <span v-if="draft.original_blog_post_id" class="text-muted-foreground ml-1 text-xs">
                                    (ID: {{ draft.original_blog_post_id }})
                                </span>
                            </TableCell>
                            <TableCell class="text-right">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
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
                                                    // TODO: Rediriger vers la page de détail du brouillon
                                                }
                                            "
                                        >
                                            <Eye class="mr-2 h-4 w-4" />
                                            <span>Voir</span>
                                        </DropdownMenuItem>

                                        <Link :href="route('dashboard.blog-posts.edit', { 'draft-id': draft.id })">
                                            <DropdownMenuItem>
                                                <Edit class="mr-2 h-4 w-4" />
                                                <span>Modifier</span>
                                            </DropdownMenuItem>
                                        </Link>

                                        <DropdownMenuSeparator />

                                        <DropdownMenuItem @click="() => publishDraft(draft.id)">
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
                        v-if="props.blogPostDrafts.length > 0"
                        v-slot="{ page }"
                        :total="props.blogPostDrafts.length"
                        :items-per-page="itemsPerPage"
                        :default-page="1"
                        show-edges
                        :sibling-count="1"
                        @update:page="handlePageChange"
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
                <div v-if="props.blogPostDrafts.length > 0" class="text-muted-foreground mt-2 text-center text-sm">
                    Affichage de {{ (currentPage - 1) * itemsPerPage + 1 }} à
                    {{ Math.min(currentPage * itemsPerPage, props.blogPostDrafts.length) }}
                    sur {{ props.blogPostDrafts.length }} brouillons
                </div>

                <div v-else class="text-muted-foreground py-10 text-center">Aucun brouillon d'article pour le moment.</div>
            </div>
        </div>
    </AppLayout>
</template>
