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
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useRoute } from '@/composables/useRoute';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BlogCategory, BreadcrumbItem } from '@/types';
import { compareValues, type SortDirection } from '@/utils/sorting';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowDown, ArrowUp, Edit, MoreHorizontal, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    blogCategories: (BlogCategory & { blog_posts_count: number; blog_post_drafts_count: number })[];
}

const props = defineProps<Props>();
const route = useRoute();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Articles',
        href: '#',
    },
    {
        title: 'Catégories',
        href: route('dashboard.blog-categories.index', undefined, false),
    },
];

// Color options from CategoryColor enum
const colorOptions = [
    { value: 'red', label: 'Rouge', hex: '#ef4444', class: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' },
    { value: 'blue', label: 'Bleu', hex: '#3b82f6', class: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' },
    { value: 'green', label: 'Vert', hex: '#22c55e', class: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' },
    { value: 'yellow', label: 'Jaune', hex: '#eab308', class: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' },
    { value: 'purple', label: 'Violet', hex: '#a855f7', class: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' },
    { value: 'pink', label: 'Rose', hex: '#ec4899', class: 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200' },
    { value: 'orange', label: 'Orange', hex: '#f97316', class: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200' },
    { value: 'gray', label: 'Gris', hex: '#6b7280', class: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200' },
];

// Pagination
const itemsPerPage = 25;
const currentPage = ref(1);

// Sorting
type SortColumn = 'id' | 'order' | 'slug';
const sortColumn = ref<SortColumn>('order');
const sortDirection = ref<SortDirection>('asc');

const toggleSort = (column: SortColumn) => {
    if (sortColumn.value === column) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn.value = column;
        sortDirection.value = 'asc';
    }
};

// Create/Edit dialog
const showCreateEditDialog = ref(false);
const editingCategory = ref<(BlogCategory & { blog_posts_count: number; blog_post_drafts_count: number }) | null>(null);
const formData = ref({
    slug: '',
    name_fr: '',
    name_en: '',
    color: 'orange',
    order: 0,
});
const formErrors = ref<Record<string, string[]>>({});

// Delete dialog
const showDeleteDialog = ref(false);
const deletingCategory = ref<(BlogCategory & { blog_posts_count: number; blog_post_drafts_count: number }) | null>(null);

// Helper functions
const getFrenchTranslation = (category: BlogCategory): string => {
    const frTranslation = category.name_translation_key.translations.find((t) => t.locale === 'fr');
    return frTranslation ? frTranslation.text : '';
};

const getEnglishTranslation = (category: BlogCategory): string => {
    const enTranslation = category.name_translation_key.translations.find((t) => t.locale === 'en');
    return enTranslation ? enTranslation.text : '';
};

const getColorBadgeClass = (color: string): string => {
    const colorOption = colorOptions.find((c) => c.value === color);
    return colorOption ? colorOption.class : colorOptions[6].class; // Default to orange
};

// Sorting and pagination
const sortedCategories = computed(() => {
    return [...props.blogCategories].sort((a, b) => {
        if (sortColumn.value === 'id' || sortColumn.value === 'order') {
            return compareValues(a[sortColumn.value], b[sortColumn.value], sortDirection.value);
        } else if (sortColumn.value === 'slug') {
            return compareValues(a.slug, b.slug, sortDirection.value);
        }
        return 0;
    });
});

const paginatedCategories = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return sortedCategories.value.slice(start, end);
});

const handlePageChange = (page: number) => {
    currentPage.value = page;
};

// Create category
const openCreateDialog = () => {
    editingCategory.value = null;
    formData.value = {
        slug: '',
        name_fr: '',
        name_en: '',
        color: 'orange',
        order: 0,
    };
    formErrors.value = {};
    showCreateEditDialog.value = true;
};

// Edit category
const openEditDialog = (category: BlogCategory & { blog_posts_count: number; blog_post_drafts_count: number }) => {
    editingCategory.value = category;
    formData.value = {
        slug: category.slug,
        name_fr: getFrenchTranslation(category),
        name_en: getEnglishTranslation(category),
        color: category.color,
        order: category.order,
    };
    formErrors.value = {};
    showCreateEditDialog.value = true;
};

// Submit create/edit
const submitCategory = async () => {
    formErrors.value = {};

    try {
        if (editingCategory.value) {
            // Update
            await axios.put(route('dashboard.api.blog-categories.update', { blog_category: editingCategory.value.id }), formData.value);
        } else {
            // Create
            await axios.post(route('dashboard.api.blog-categories.store'), formData.value);
        }
        showCreateEditDialog.value = false;
        router.reload();
    } catch (error: any) {
        if (error.response?.status === 422) {
            formErrors.value = error.response.data.errors || {};
        } else {
            console.error('Erreur lors de la sauvegarde:', error);
        }
    }
};

// Delete category
const openDeleteDialog = (category: BlogCategory & { blog_posts_count: number; blog_post_drafts_count: number }) => {
    deletingCategory.value = category;
    showDeleteDialog.value = true;
};

const confirmDelete = async () => {
    if (!deletingCategory.value) return;

    try {
        await axios.delete(route('dashboard.api.blog-categories.destroy', { blog_category: deletingCategory.value.id }));
        showDeleteDialog.value = false;
        deletingCategory.value = null;
        router.reload();
    } catch (error: any) {
        if (error.response?.status === 422) {
            // Category is used, keep dialog open to show error
            console.error('Cannot delete category:', error.response.data.message);
        } else {
            console.error('Erreur lors de la suppression:', error);
        }
    }
};

const canDeleteCategory = computed(() => {
    if (!deletingCategory.value) return false;
    return deletingCategory.value.blog_posts_count === 0 && deletingCategory.value.blog_post_drafts_count === 0;
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Catégories d'articles" />
        <div class="px-5 py-6">
            <div class="flex items-center justify-between">
                <Heading title="Catégories d'articles" description="Gérez les catégories pour vos articles de blog." />
                <Button @click="openCreateDialog">
                    <Plus class="mr-2 h-4 w-4" />
                    Nouvelle catégorie
                </Button>
            </div>

            <div class="py-4">
                <Table>
                    <TableCaption>Liste des catégories d'articles</TableCaption>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-[80px] cursor-pointer" @click="toggleSort('id')">
                                <div class="flex items-center">
                                    ID
                                    <ArrowUp v-if="sortColumn === 'id' && sortDirection === 'asc'" class="ml-1 h-4 w-4" />
                                    <ArrowDown v-if="sortColumn === 'id' && sortDirection === 'desc'" class="ml-1 h-4 w-4" />
                                </div>
                            </TableHead>
                            <TableHead class="cursor-pointer" @click="toggleSort('slug')">
                                <div class="flex items-center">
                                    Slug
                                    <ArrowUp v-if="sortColumn === 'slug' && sortDirection === 'asc'" class="ml-1 h-4 w-4" />
                                    <ArrowDown v-if="sortColumn === 'slug' && sortDirection === 'desc'" class="ml-1 h-4 w-4" />
                                </div>
                            </TableHead>
                            <TableHead>Nom (FR)</TableHead>
                            <TableHead>Nom (EN)</TableHead>
                            <TableHead>Couleur</TableHead>
                            <TableHead class="w-[100px] cursor-pointer" @click="toggleSort('order')">
                                <div class="flex items-center">
                                    Ordre
                                    <ArrowUp v-if="sortColumn === 'order' && sortDirection === 'asc'" class="ml-1 h-4 w-4" />
                                    <ArrowDown v-if="sortColumn === 'order' && sortDirection === 'desc'" class="ml-1 h-4 w-4" />
                                </div>
                            </TableHead>
                            <TableHead class="text-center">Articles</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="category in paginatedCategories" :key="category.id">
                            <TableCell class="font-medium">{{ category.id }}</TableCell>
                            <TableCell>{{ category.slug }}</TableCell>
                            <TableCell>{{ getFrenchTranslation(category) }}</TableCell>
                            <TableCell>{{ getEnglishTranslation(category) }}</TableCell>
                            <TableCell>
                                <Badge :class="getColorBadgeClass(category.color)">{{ category.color }}</Badge>
                            </TableCell>
                            <TableCell>{{ category.order }}</TableCell>
                            <TableCell class="text-center">{{ category.blog_posts_count + category.blog_post_drafts_count }}</TableCell>
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
                                        <DropdownMenuItem @click="openEditDialog(category)">
                                            <Edit class="mr-2 h-4 w-4" />
                                            <span>Modifier</span>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem class="text-destructive" @click="openDeleteDialog(category)">
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
                <div v-if="props.blogCategories.length > itemsPerPage" class="mt-6 flex justify-center">
                    <Pagination
                        v-slot="{ page }"
                        :total="props.blogCategories.length"
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
                <div class="text-muted-foreground mt-2 text-center text-sm">
                    Affichage de {{ (currentPage - 1) * itemsPerPage + 1 }} à
                    {{ Math.min(currentPage * itemsPerPage, props.blogCategories.length) }}
                    sur {{ props.blogCategories.length }} catégories
                </div>
            </div>
        </div>

        <!-- Create/Edit Dialog -->
        <Dialog :open="showCreateEditDialog" @update:open="showCreateEditDialog = $event">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>{{ editingCategory ? 'Modifier la catégorie' : 'Nouvelle catégorie' }}</DialogTitle>
                    <DialogDescription>
                        {{ editingCategory ? 'Modifiez les informations de la catégorie.' : 'Créez une nouvelle catégorie pour vos articles.' }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label for="slug">Slug</Label>
                        <Input id="slug" v-model="formData.slug" placeholder="ma-categorie" />
                        <p v-if="formErrors.slug" class="text-destructive text-sm">{{ formErrors.slug[0] }}</p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="name_fr">Nom (Français)</Label>
                        <Input id="name_fr" v-model="formData.name_fr" placeholder="Ma Catégorie" />
                        <p v-if="formErrors.name_fr" class="text-destructive text-sm">{{ formErrors.name_fr[0] }}</p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="name_en">Nom (Anglais)</Label>
                        <Input id="name_en" v-model="formData.name_en" placeholder="My Category" />
                        <p v-if="formErrors.name_en" class="text-destructive text-sm">{{ formErrors.name_en[0] }}</p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="color">Couleur</Label>
                        <Select v-model="formData.color">
                            <SelectTrigger id="color">
                                <SelectValue placeholder="Sélectionner une couleur" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="color in colorOptions" :key="color.value" :value="color.value">
                                    <div class="flex items-center gap-2">
                                        <div class="h-4 w-4 rounded" :style="{ backgroundColor: color.hex }"></div>
                                        {{ color.label }}
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="formErrors.color" class="text-destructive text-sm">{{ formErrors.color[0] }}</p>
                    </div>

                    <div v-if="editingCategory" class="grid gap-2">
                        <Label for="order">Ordre</Label>
                        <Input id="order" v-model.number="formData.order" type="number" min="0" />
                        <p v-if="formErrors.order" class="text-destructive text-sm">{{ formErrors.order[0] }}</p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showCreateEditDialog = false">Annuler</Button>
                    <Button @click="submitCategory">{{ editingCategory ? 'Enregistrer' : 'Créer' }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <AlertDialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Confirmer la suppression</AlertDialogTitle>
                    <AlertDialogDescription v-if="deletingCategory">
                        <template v-if="canDeleteCategory">
                            Êtes-vous sûr de vouloir supprimer la catégorie
                            <strong>{{ getFrenchTranslation(deletingCategory) }}</strong> ? Cette action est irréversible.
                        </template>
                        <template v-else>
                            Impossible de supprimer la catégorie <strong>{{ getFrenchTranslation(deletingCategory) }}</strong> car elle est utilisée
                            par <strong>{{ deletingCategory.blog_posts_count }}</strong> article(s) publié(s) et
                            <strong>{{ deletingCategory.blog_post_drafts_count }}</strong> brouillon(s). <br /><br />
                            Veuillez d'abord modifier ou supprimer ces articles avant de supprimer cette catégorie.
                        </template>
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="showDeleteDialog = false">Annuler</AlertDialogCancel>
                    <AlertDialogAction v-if="canDeleteCategory" class="bg-destructive hover:bg-destructive/90" @click="confirmDelete">
                        Supprimer
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
