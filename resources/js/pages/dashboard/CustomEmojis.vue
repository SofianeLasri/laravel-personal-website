<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { AlertCircle, Image as ImageIcon, Plus, Smile, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface CustomEmoji {
    id: number;
    name: string;
    picture_id: number;
    picture: {
        id: number;
        filename: string;
        path_original: string;
        optimized_pictures: Array<{
            id: number;
            path: string;
            format: string;
            size: string;
        }>;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Emojis personnalisés',
        href: '/dashboard/custom-emojis',
    },
];

// State
const emojis = ref<CustomEmoji[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const success = ref<string | null>(null);

// Create form state
const showCreateDialog = ref(false);
const createForm = ref({
    name: '',
    file: null as File | null,
});
const createFormErrors = ref<Record<string, string>>({});
const fileInput = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(null);

// Delete confirmation
const showDeleteDialog = ref(false);
const emojiToDelete = ref<CustomEmoji | null>(null);

// Computed
const hasEmojis = computed(() => emojis.value.length > 0);

// Load emojis
const loadEmojis = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get('/dashboard/api/custom-emojis');
        emojis.value = response.data;
    } catch (err) {
        error.value = 'Erreur lors du chargement des emojis';
        console.error(err);
    } finally {
        loading.value = false;
    }
};

// Handle file selection
const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        const file = target.files[0];
        createForm.value.file = file;

        // Create preview
        const reader = new FileReader();
        reader.onload = (e) => {
            previewUrl.value = e.target?.result as string;
        };
        reader.readAsDataURL(file);
    }
};

// Trigger file input
const triggerFileInput = () => {
    fileInput.value?.click();
};

// Reset create form
const resetCreateForm = () => {
    createForm.value = {
        name: '',
        file: null,
    };
    createFormErrors.value = {};
    previewUrl.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

// Create emoji
const createEmoji = async () => {
    if (!createForm.value.file) {
        createFormErrors.value.file = 'L\'image est requise';
        return;
    }

    loading.value = true;
    error.value = null;
    success.value = null;
    createFormErrors.value = {};

    try {
        const formData = new FormData();
        formData.append('name', createForm.value.name);
        formData.append('picture', createForm.value.file);

        await axios.post('/dashboard/api/custom-emojis', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        success.value = 'Emoji créé avec succès';
        showCreateDialog.value = false;
        resetCreateForm();
        await loadEmojis();
    } catch (err: any) {
        if (err.response?.data?.errors) {
            createFormErrors.value = err.response.data.errors;
        } else {
            error.value = err.response?.data?.message || 'Erreur lors de la création de l\'emoji';
        }
    } finally {
        loading.value = false;
    }
};

// Confirm delete
const confirmDelete = (emoji: CustomEmoji) => {
    emojiToDelete.value = emoji;
    showDeleteDialog.value = true;
};

// Delete emoji
const deleteEmoji = async () => {
    if (!emojiToDelete.value) return;

    loading.value = true;
    error.value = null;
    success.value = null;

    try {
        await axios.delete(`/dashboard/api/custom-emojis/${emojiToDelete.value.id}`);
        success.value = 'Emoji supprimé avec succès';
        showDeleteDialog.value = false;
        emojiToDelete.value = null;
        await loadEmojis();
    } catch (err) {
        error.value = 'Erreur lors de la suppression de l\'emoji';
        console.error(err);
    } finally {
        loading.value = false;
    }
};

// Get emoji preview URL
const getEmojiPreviewUrl = (emoji: CustomEmoji): string => {
    // Try to get thumbnail WebP first
    const webpThumbnail = emoji.picture.optimized_pictures?.find(
        (opt) => opt.format === 'webp' && opt.size === 'thumbnail'
    );
    if (webpThumbnail) {
        return `/storage/${webpThumbnail.path}`;
    }

    // Fallback to PNG thumbnail
    const pngThumbnail = emoji.picture.optimized_pictures?.find(
        (opt) => opt.format === 'png' && opt.size === 'thumbnail'
    );
    if (pngThumbnail) {
        return `/storage/${pngThumbnail.path}`;
    }

    // Fallback to original
    return `/storage/${emoji.picture.path_original}`;
};

onMounted(() => {
    void loadEmojis();
});
</script>

<template>
    <AppLayout title="Emojis personnalisés" :breadcrumbs="breadcrumbs">
        <Head title="Emojis personnalisés" />

        <div class="space-y-6 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Emojis personnalisés</h1>
                    <p class="text-muted-foreground mt-2">
                        Gérez vos emojis personnalisés pour les articles de blog
                    </p>
                </div>
                <Button @click="showCreateDialog = true">
                    <Plus class="mr-2 h-4 w-4" />
                    Ajouter un emoji
                </Button>
            </div>

            <!-- Alerts -->
            <Alert v-if="error" variant="destructive">
                <AlertCircle class="h-4 w-4" />
                <AlertDescription>{{ error }}</AlertDescription>
            </Alert>

            <Alert v-if="success">
                <AlertDescription>{{ success }}</AlertDescription>
            </Alert>

            <!-- Emojis Grid -->
            <Card>
                <CardHeader>
                    <CardTitle>Emojis disponibles</CardTitle>
                    <CardDescription>
                        Utilisez la syntaxe <code class="bg-muted rounded px-1">:emoji_name:</code> dans vos articles
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="loading && !hasEmojis" class="flex items-center justify-center py-8">
                        <div class="border-primary h-8 w-8 animate-spin rounded-full border-b-2"></div>
                    </div>

                    <div v-else-if="!hasEmojis" class="flex flex-col items-center justify-center py-12 text-center">
                        <Smile class="text-muted-foreground mb-4 h-12 w-12" />
                        <h3 class="mb-2 text-lg font-semibold">Aucun emoji personnalisé</h3>
                        <p class="text-muted-foreground mb-4">Commencez par ajouter votre premier emoji</p>
                        <Button @click="showCreateDialog = true">
                            <Plus class="mr-2 h-4 w-4" />
                            Ajouter un emoji
                        </Button>
                    </div>

                    <div v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                        <div
                            v-for="emoji in emojis"
                            :key="emoji.id"
                            class="border-input hover:border-primary group relative flex flex-col items-center gap-2 rounded-lg border p-4 transition-colors"
                        >
                            <div class="bg-muted flex h-16 w-16 items-center justify-center rounded-md">
                                <img
                                    :src="getEmojiPreviewUrl(emoji)"
                                    :alt="emoji.name"
                                    class="h-full w-full object-contain"
                                />
                            </div>
                            <code class="text-sm">:{{ emoji.name }}:</code>
                            <Button
                                variant="destructive"
                                size="icon"
                                class="absolute right-2 top-2 h-6 w-6 opacity-0 transition-opacity group-hover:opacity-100"
                                @click="confirmDelete(emoji)"
                            >
                                <Trash2 class="h-3 w-3" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Create Dialog -->
        <Dialog v-model:open="showCreateDialog" @update:open="(open) => !open && resetCreateForm()">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Ajouter un emoji personnalisé</DialogTitle>
                    <DialogDescription>
                        Uploadez une image et donnez-lui un nom unique
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <!-- Name Input -->
                    <div class="space-y-2">
                        <Label for="name">Nom de l'emoji</Label>
                        <Input
                            id="name"
                            v-model="createForm.name"
                            placeholder="fire, rocket, custom_name..."
                            :class="{ 'border-destructive': createFormErrors.name }"
                        />
                        <p v-if="createFormErrors.name" class="text-destructive text-sm">
                            {{ createFormErrors.name[0] }}
                        </p>
                        <p class="text-muted-foreground text-xs">
                            Lettres, chiffres et underscores uniquement
                        </p>
                    </div>

                    <!-- File Input -->
                    <div class="space-y-2">
                        <Label>Image</Label>
                        <div class="flex items-center gap-4">
                            <div
                                class="border-input bg-muted flex h-20 w-20 items-center justify-center overflow-hidden rounded-md border"
                            >
                                <img
                                    v-if="previewUrl"
                                    :src="previewUrl"
                                    alt="Preview"
                                    class="h-full w-full object-contain"
                                />
                                <ImageIcon v-else class="text-muted-foreground h-8 w-8" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <Button variant="outline" size="sm" type="button" @click="triggerFileInput">
                                    {{ createForm.file ? 'Changer' : 'Choisir' }} l'image
                                </Button>
                                <p v-if="createForm.file" class="text-sm">{{ createForm.file.name }}</p>
                            </div>
                        </div>
                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/png,image/jpeg,image/webp,image/svg+xml"
                            class="hidden"
                            @change="handleFileChange"
                        />
                        <p v-if="createFormErrors.picture" class="text-destructive text-sm">
                            {{ createFormErrors.picture[0] }}
                        </p>
                        <p class="text-muted-foreground text-xs">PNG, JPG, WebP ou SVG (max 500KB)</p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showCreateDialog = false">Annuler</Button>
                    <Button @click="createEmoji" :disabled="loading">Créer</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="showDeleteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Supprimer l'emoji</DialogTitle>
                    <DialogDescription>
                        Êtes-vous sûr de vouloir supprimer l'emoji
                        <code class="bg-muted rounded px-1">:{{ emojiToDelete?.name }}:</code> ?
                        Cette action est irréversible.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <Button variant="outline" @click="showDeleteDialog = false">Annuler</Button>
                    <Button variant="destructive" @click="deleteEmoji" :disabled="loading">Supprimer</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
