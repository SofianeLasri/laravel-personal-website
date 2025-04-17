<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import PictureInput from '@/components/PictureInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import axios from 'axios';
import { Loader2, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';

interface Feature {
    id: number;
    creation_draft_id: number;
    title_translation_key_id: number;
    description_translation_key_id: number;
    picture_id: number | null;
    created_at: string;
    updated_at: string;
    picture?: {
        id: number;
        filename: string;
        path_original: string;
    };
    title_translation_key?: {
        id: number;
        key: string;
        translations: {
            id: number;
            translation_key_id: number;
            locale: string;
            text: string;
        }[];
    };
    description_translation_key?: {
        id: number;
        key: string;
        translations: {
            id: number;
            translation_key_id: number;
            locale: string;
            text: string;
        }[];
    };
}

const props = defineProps<{
    creationDraftId: number | null;
    locale: string;
}>();

const features = ref<Feature[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const isAddModalOpen = ref(false);
const isEditModalOpen = ref(false);
const selectedFeature = ref<Feature | null>(null);
const newFeaturePictureId = ref<number | undefined>(undefined);
const newFeatureTitle = ref('');
const newFeatureDescription = ref('');
const editFeatureTitle = ref('');
const editFeatureDescription = ref('');
const editFeaturePictureId = ref<number | undefined>(undefined);

const fetchFeatures = async () => {
    if (!props.creationDraftId) return;

    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get(
            route('dashboard.api.creation-drafts.draft-features.index', {
                creation_draft: props.creationDraftId,
            }),
        );
        features.value = response.data;
    } catch (err) {
        error.value = 'Erreur lors du chargement des fonctionnalités clés';
        console.error(err);
    } finally {
        loading.value = false;
    }
};

const addFeature = async () => {
    if (!props.creationDraftId || !newFeatureTitle.value || !newFeatureDescription.value) {
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        const data = {
            locale: props.locale,
            title: newFeatureTitle.value,
            description: newFeatureDescription.value,
            picture_id: newFeaturePictureId.value,
        };

        await axios.post(
            route('dashboard.api.creation-drafts.draft-features.store', {
                creation_draft: props.creationDraftId,
            }),
            data,
        );

        await fetchFeatures();
        resetNewFeatureForm();
        isAddModalOpen.value = false;
    } catch (err) {
        error.value = "Erreur lors de l'ajout de la fonctionnalité clé";
        console.error(err);
    } finally {
        loading.value = false;
    }
};

const updateFeature = async () => {
    if (!selectedFeature.value) return;

    loading.value = true;
    error.value = null;

    try {
        await axios.put(
            route('dashboard.api.draft-features.update', {
                draft_feature: selectedFeature.value.id,
            }),
            {
                locale: props.locale,
                title: editFeatureTitle.value,
                description: editFeatureDescription.value,
                picture_id: editFeaturePictureId.value,
            },
        );

        await fetchFeatures();
        isEditModalOpen.value = false;
    } catch (err) {
        error.value = 'Erreur lors de la mise à jour de la fonctionnalité';
        console.error(err);
    } finally {
        loading.value = false;
    }
};

const deleteFeature = async (feature: Feature) => {
    if (!confirm('Êtes-vous sûr de vouloir supprimer cette fonctionnalité clé ?')) {
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        await axios.delete(
            route('dashboard.api.draft-features.destroy', {
                draft_feature: feature.id,
            }),
        );

        await fetchFeatures();
    } catch (err) {
        error.value = 'Erreur lors de la suppression de la fonctionnalité';
        console.error(err);
    } finally {
        loading.value = false;
    }
};

const openEditModal = (feature: Feature) => {
    selectedFeature.value = feature;

    const titleTranslation = feature.title_translation_key?.translations.find((t) => t.locale === props.locale);
    const descriptionTranslation = feature.description_translation_key?.translations.find((t) => t.locale === props.locale);

    editFeatureTitle.value = titleTranslation?.text || '';
    editFeatureDescription.value = descriptionTranslation?.text || '';
    editFeaturePictureId.value = feature.picture_id || undefined;
    isEditModalOpen.value = true;
};

const resetNewFeatureForm = () => {
    newFeaturePictureId.value = undefined;
    newFeatureTitle.value = '';
    newFeatureDescription.value = '';
};

const getFeatureTitle = (feature: Feature): string => {
    if (!feature.title_translation_key) return '';

    const translation = feature.title_translation_key.translations.find((t) => t.locale === props.locale);

    return translation?.text || '';
};

const getFeatureDescription = (feature: Feature): string => {
    if (!feature.description_translation_key) return '';

    const translation = feature.description_translation_key.translations.find((t) => t.locale === props.locale);

    return translation?.text || '';
};

onMounted(() => {
    if (props.creationDraftId) {
        fetchFeatures();
    }
});

watch([() => props.creationDraftId, () => props.locale], async ([newDraftId, newLocale], [oldDraftId, oldLocale]) => {
    if (newDraftId && (newDraftId !== oldDraftId || newLocale !== oldLocale)) {
        await fetchFeatures();
    }
});
</script>

<template>
    <div class="space-y-6">
        <HeadingSmall title="Fonctionnalités clés" description="Ajoutez les fonctionnalités principales de votre création." />

        <div v-if="error" class="mb-4 rounded-md bg-destructive/10 p-4 text-sm text-destructive">
            {{ error }}
        </div>

        <div v-if="!props.creationDraftId" class="rounded-md bg-muted p-4 text-sm text-muted-foreground">
            Veuillez d'abord enregistrer le brouillon pour pouvoir ajouter des fonctionnalités clés.
        </div>

        <div v-else>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card class="cursor-pointer border-dashed transition-colors hover:bg-muted/50" @click="isAddModalOpen = true">
                    <CardContent class="flex h-full min-h-[200px] flex-col items-center justify-center p-6">
                        <Plus class="mb-2 h-12 w-12 text-muted-foreground" />
                        <p class="text-sm text-muted-foreground">Ajouter une fonctionnalité clé</p>
                    </CardContent>
                </Card>

                <Card v-for="feature in features" :key="feature.id" class="overflow-hidden">
                    <div v-if="feature.picture" class="relative aspect-video bg-muted">
                        <img :src="`/storage/${feature.picture.path_original}`" :alt="getFeatureTitle(feature)" class="h-full w-full object-cover" />
                    </div>
                    <CardContent class="p-4">
                        <div class="flex items-start justify-between">
                            <div class="min-w-0 flex-1">
                                <h3 class="truncate text-sm font-medium">{{ getFeatureTitle(feature) }}</h3>
                                <p class="mt-1 line-clamp-3 text-xs text-muted-foreground">
                                    {{ getFeatureDescription(feature) }}
                                </p>
                            </div>
                            <div class="ml-2 flex flex-shrink-0 space-x-1">
                                <Button variant="ghost" size="icon" @click.stop="openEditModal(feature)" title="Modifier">
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button variant="ghost" size="icon" @click.stop="deleteFeature(feature)" title="Supprimer">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div v-if="features.length === 0 && !loading" class="py-8 text-center text-muted-foreground">
                <p>Aucune fonctionnalité clé ajoutée</p>
                <Button variant="outline" class="mt-4" @click="isAddModalOpen = true"> Ajouter une fonctionnalité </Button>
            </div>

            <div v-if="loading" class="flex justify-center py-8">
                <Loader2 class="h-8 w-8 animate-spin text-primary" />
            </div>
        </div>

        <Dialog v-model:open="isAddModalOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Ajouter une fonctionnalité clé</DialogTitle>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Titre ({{ props.locale }})</label>
                        <Input v-model="newFeatureTitle" placeholder="Titre de la fonctionnalité" />
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Description ({{ props.locale }})</label>
                        <Textarea v-model="newFeatureDescription" placeholder="Description de la fonctionnalité" />
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Image (optionnelle)</label>
                        <PictureInput v-model="newFeaturePictureId" />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="isAddModalOpen = false" :disabled="loading">Annuler</Button>
                    <Button :disabled="!newFeatureTitle || !newFeatureDescription || loading" @click="addFeature">
                        <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                        Ajouter
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="isEditModalOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Modifier la fonctionnalité</DialogTitle>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div v-if="selectedFeature && selectedFeature.picture" class="relative aspect-video w-full overflow-hidden rounded-lg bg-muted">
                        <img
                            :src="`/storage/${selectedFeature.picture.path_original}`"
                            :alt="editFeatureTitle"
                            class="h-full w-full object-contain"
                        />
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Titre ({{ props.locale }})</label>
                        <Input v-model="editFeatureTitle" placeholder="Titre de la fonctionnalité" />
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Description ({{ props.locale }})</label>
                        <Textarea v-model="editFeatureDescription" placeholder="Description de la fonctionnalité" />
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Image (optionnelle)</label>
                        <PictureInput v-model="editFeaturePictureId" />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="isEditModalOpen = false" :disabled="loading">Annuler</Button>
                    <Button :disabled="!editFeatureTitle || !editFeatureDescription || loading" @click="updateFeature">
                        <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                        Enregistrer
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
