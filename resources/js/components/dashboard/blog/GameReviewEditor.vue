<script setup lang="ts">
import PictureInput from '@/components/dashboard/media/PictureInput.vue';
import HeadingSmall from '@/components/dashboard/shared/ui/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useRoute } from '@/composables/useRoute';
import axios from 'axios';
import { Plus, Trash2 } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

interface GameReviewLink {
    id?: number;
    type: string;
    url: string;
    label?: string;
    order: number;
}

interface GameReviewDraft {
    id: number;
    game_title: string;
    release_date: string | null;
    genre: string;
    developer: string;
    publisher: string;
    platforms: string[];
    cover_picture_id: number | null;
    pros_translation_key_id: number | null;
    cons_translation_key_id: number | null;
    rating: 'positive' | 'negative' | null;
    pros_translation_key?: {
        translations: Array<{
            locale: string;
            text: string;
        }>;
    };
    cons_translation_key?: {
        translations: Array<{
            locale: string;
            text: string;
        }>;
    };
    links?: GameReviewLink[];
}

interface Props {
    draftId?: number;
    gameReviewDraft?: GameReviewDraft;
    locale: 'fr' | 'en';
}

const props = defineProps<Props>();
const route = useRoute();

const gameData = ref({
    game_title: props.gameReviewDraft?.game_title ?? '',
    release_date: props.gameReviewDraft?.release_date ?? '',
    genre: props.gameReviewDraft?.genre ?? '',
    developer: props.gameReviewDraft?.developer ?? '',
    publisher: props.gameReviewDraft?.publisher ?? '',
    platforms: props.gameReviewDraft?.platforms?.join(', ') ?? '',
    cover_picture_id: props.gameReviewDraft?.cover_picture_id ?? null,
    rating: props.gameReviewDraft?.rating ?? null,
    pros: '',
    cons: '',
});

const links = ref<GameReviewLink[]>(props.gameReviewDraft?.links ?? []);

const linkTypes = [
    { value: 'steam', label: 'Steam' },
    { value: 'epic', label: 'Epic Games' },
    { value: 'playstation', label: 'PlayStation' },
    { value: 'xbox', label: 'Xbox' },
    { value: 'nintendo', label: 'Nintendo' },
    { value: 'official', label: 'Site officiel' },
    { value: 'other', label: 'Autre' },
];

// Load initial translations
onMounted(() => {
    if (props.gameReviewDraft) {
        const prosTranslation = props.gameReviewDraft.pros_translation_key?.translations?.find((t) => t.locale === props.locale);
        const consTranslation = props.gameReviewDraft.cons_translation_key?.translations?.find((t) => t.locale === props.locale);

        gameData.value.pros = prosTranslation?.text ?? '';
        gameData.value.cons = consTranslation?.text ?? '';
    }
});

const saveGameReview = async () => {
    if (!props.draftId) {
        toast.error("Veuillez d'abord sauvegarder le brouillon");
        return;
    }

    try {
        const payload = {
            ...gameData.value,
            platforms: gameData.value.platforms
                .split(',')
                .map((p) => p.trim())
                .filter((p) => p),
            [`pros_${props.locale}`]: gameData.value.pros,
            [`cons_${props.locale}`]: gameData.value.cons,
        };

        if (props.gameReviewDraft?.id) {
            // Update existing game review draft
            await axios.put(
                route('dashboard.api.game-review-drafts.update', {
                    game_review_draft: props.gameReviewDraft.id,
                }),
                payload,
            );
        } else {
            // Create new game review draft
            await axios.post(route('dashboard.api.game-review-drafts.store'), {
                ...payload,
                blog_post_draft_id: props.draftId,
            });
        }

        toast.success('Informations du jeu sauvegardées');
    } catch (error) {
        console.error('Erreur lors de la sauvegarde:', error);
        toast.error('Erreur lors de la sauvegarde');
    }
};

const addLink = () => {
    links.value.push({
        type: 'official',
        url: '',
        label: '',
        order: links.value.length,
    });
};

const removeLink = async (index: number) => {
    const link = links.value[index];

    if (link.id && props.gameReviewDraft?.id) {
        try {
            await axios.delete(
                route('dashboard.api.game-review-draft-links.destroy', {
                    game_review_draft_link: link.id,
                }),
            );
            toast.success('Lien supprimé');
        } catch (error) {
            console.error('Erreur lors de la suppression:', error);
            toast.error('Erreur lors de la suppression');
        }
    }

    links.value.splice(index, 1);
};

const saveLink = async (link: GameReviewLink) => {
    if (!props.gameReviewDraft?.id) {
        toast.error("Veuillez d'abord sauvegarder les informations du jeu");
        return;
    }

    try {
        if (link.id) {
            // Update existing link
            await axios.put(
                route('dashboard.api.game-review-draft-links.update', {
                    game_review_draft_link: link.id,
                }),
                link,
            );
        } else {
            // Create new link
            const response = await axios.post(route('dashboard.api.game-review-draft-links.store'), {
                ...link,
                game_review_draft_id: props.gameReviewDraft.id,
            });
            link.id = response.data.id;
        }

        toast.success('Lien sauvegardé');
    } catch (error) {
        console.error('Erreur lors de la sauvegarde du lien:', error);
        toast.error('Erreur lors de la sauvegarde');
    }
};

const handleCoverChange = (pictureId: number | null) => {
    gameData.value.cover_picture_id = pictureId;
};
</script>

<template>
    <div class="space-y-6 border-t pt-6">
        <HeadingSmall title="Informations du jeu" description="Détails spécifiques pour la critique de jeu" />

        <div class="grid grid-cols-2 gap-4">
            <div>
                <Label>Titre du jeu</Label>
                <Input
                    v-model="gameData.game_title"
                    placeholder="Ex: The Legend of Zelda: Tears of the Kingdom"
                    data-form-type="other"
                    @blur="saveGameReview"
                />
            </div>

            <div>
                <Label>Date de sortie</Label>
                <Input v-model="gameData.release_date" type="date" data-form-type="other" @blur="saveGameReview" />
            </div>

            <div>
                <Label>Genre</Label>
                <Input v-model="gameData.genre" placeholder="Ex: Action-RPG, FPS, Stratégie..." data-form-type="other" @blur="saveGameReview" />
            </div>

            <div>
                <Label>Appréciation</Label>
                <div class="mt-2 flex gap-4">
                    <label class="flex cursor-pointer items-center gap-2">
                        <input v-model="gameData.rating" type="radio" value="positive" class="text-green-600" @change="saveGameReview" />
                        <span class="text-green-600">Review positive</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-2">
                        <input v-model="gameData.rating" type="radio" value="negative" class="text-red-600" @change="saveGameReview" />
                        <span class="text-red-600">Review négative</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-2">
                        <input v-model="gameData.rating" type="radio" :value="null" class="text-gray-400" @change="saveGameReview" />
                        <span class="text-gray-400">Non défini</span>
                    </label>
                </div>
            </div>

            <div>
                <Label>Développeur</Label>
                <Input v-model="gameData.developer" placeholder="Ex: Nintendo EPD" data-form-type="other" @blur="saveGameReview" />
            </div>

            <div>
                <Label>Éditeur</Label>
                <Input v-model="gameData.publisher" placeholder="Ex: Nintendo" data-form-type="other" @blur="saveGameReview" />
            </div>

            <div class="col-span-2">
                <Label>Plateformes</Label>
                <Input
                    v-model="gameData.platforms"
                    placeholder="Ex: Nintendo Switch, PC, PlayStation 5 (séparées par des virgules)"
                    data-form-type="other"
                    @blur="saveGameReview"
                />
            </div>

            <div class="col-span-2">
                <Label>Image de couverture du jeu</Label>
                <PictureInput :picture-id="gameData.cover_picture_id" label="Pochette du jeu" @update:picture-id="handleCoverChange" />
            </div>
        </div>

        <div class="space-y-4">
            <div>
                <Label>Points positifs ({{ locale.toUpperCase() }})</Label>
                <Textarea
                    v-model="gameData.pros"
                    placeholder="Liste des points positifs en Markdown..."
                    class="min-h-[100px] font-mono"
                    @blur="saveGameReview"
                />
            </div>

            <div>
                <Label>Points négatifs ({{ locale.toUpperCase() }})</Label>
                <Textarea
                    v-model="gameData.cons"
                    placeholder="Liste des points négatifs en Markdown..."
                    class="min-h-[100px] font-mono"
                    @blur="saveGameReview"
                />
            </div>
        </div>

        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <Label>Liens d'achat / Information</Label>
                <Button type="button" size="sm" variant="outline" @click="addLink">
                    <Plus class="mr-2 h-4 w-4" />
                    Ajouter un lien
                </Button>
            </div>

            <div v-for="(link, index) in links" :key="index" class="flex items-end gap-2">
                <div class="flex-1">
                    <Label>Type</Label>
                    <select v-model="link.type" class="border-input bg-background w-full rounded-md border px-3 py-2" @change="saveLink(link)">
                        <option v-for="type in linkTypes" :key="type.value" :value="type.value">
                            {{ type.label }}
                        </option>
                    </select>
                </div>

                <div class="flex-[2]">
                    <Label>URL</Label>
                    <Input v-model="link.url" placeholder="https://..." data-form-type="other" @blur="saveLink(link)" />
                </div>

                <div class="flex-1">
                    <Label>Label (optionnel)</Label>
                    <Input v-model="link.label" placeholder="Texte du lien" data-form-type="other" @blur="saveLink(link)" />
                </div>

                <Button type="button" size="icon" variant="ghost" @click="removeLink(index)">
                    <Trash2 class="h-4 w-4" />
                </Button>
            </div>
        </div>
    </div>
</template>
