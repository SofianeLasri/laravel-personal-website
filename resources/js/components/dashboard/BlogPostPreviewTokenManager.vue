<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useRoute } from '@/composables/useRoute';
import axios from 'axios';
import { Clock, Copy, ExternalLink, RefreshCw, Trash2 } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    draftId: number;
}

interface PreviewToken {
    id: number;
    token: string;
    url: string;
    expires_at: string;
    expires_at_human: string;
}

const props = defineProps<Props>();
const route = useRoute();

const previewToken = ref<PreviewToken | null>(null);
const isLoading = ref(false);
const isGenerating = ref(false);
const isRevoking = ref(false);

const loadPreviewToken = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get(route('dashboard.api.blog-post-preview-tokens.show', { blog_post_draft: props.draftId }));
        if (response.data.success && response.data.data) {
            previewToken.value = response.data.data;
        }
    } catch (error: unknown) {
        const axiosError = error as { response?: { status?: number } };
        if (axiosError.response?.status !== 404) {
            console.error('Error loading preview token:', error);
        }
        previewToken.value = null;
    } finally {
        isLoading.value = false;
    }
};

const generatePreviewToken = async () => {
    isGenerating.value = true;
    try {
        const response = await axios.post(route('dashboard.api.blog-post-preview-tokens.store', { blog_post_draft: props.draftId }), {
            expires_in_days: 7,
        });

        if (response.data.success && response.data.data) {
            previewToken.value = response.data.data;
            toast.success(response.data.message ?? 'Lien de prévisualisation généré avec succès');
        }
    } catch (error) {
        console.error('Error generating preview token:', error);
        toast.error('Erreur lors de la génération du lien de prévisualisation');
    } finally {
        isGenerating.value = false;
    }
};

const revokePreviewToken = async () => {
    if (!previewToken.value) return;

    isRevoking.value = true;
    try {
        await axios.delete(route('dashboard.api.blog-post-preview-tokens.destroy', { blog_post_preview_token: previewToken.value.id }));
        previewToken.value = null;
        toast.success('Lien de prévisualisation révoqué avec succès');
    } catch (error) {
        console.error('Error revoking preview token:', error);
        toast.error('Erreur lors de la révocation du lien');
    } finally {
        isRevoking.value = false;
    }
};

const copyToClipboard = () => {
    if (!previewToken.value) return;

    void navigator.clipboard.writeText(previewToken.value.url).then(() => {
        toast.success('Lien copié dans le presse-papier');
    }).catch((error: unknown) => {
        console.error('Error copying to clipboard:', error);
        toast.error('Erreur lors de la copie du lien');
    });
};

const openPreview = () => {
    if (!previewToken.value) return;
    window.open(previewToken.value.url, '_blank');
};

onMounted(() => {
    void loadPreviewToken();
});
</script>

<template>
    <div class="space-y-4 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900/20">
        <div class="flex items-center justify-between">
            <div>
                <h4 class="text-sm font-medium">Lien de prévisualisation</h4>
                <p class="text-muted-foreground text-xs">Partagez un lien temporaire pour prévisualiser cet article</p>
            </div>
            <Button
                v-if="!previewToken && !isLoading"
                type="button"
                size="sm"
                variant="outline"
                :disabled="isGenerating"
                @click="generatePreviewToken"
            >
                <RefreshCw v-if="isGenerating" class="mr-2 h-4 w-4 animate-spin" />
                {{ isGenerating ? 'Génération...' : 'Générer un lien' }}
            </Button>
            <Button v-if="previewToken" type="button" size="sm" variant="outline" :disabled="isGenerating" @click="generatePreviewToken">
                <RefreshCw v-if="isGenerating" class="mr-2 h-4 w-4 animate-spin" />
                {{ isGenerating ? 'Régénération...' : 'Régénérer' }}
            </Button>
        </div>

        <div v-if="isLoading" class="flex items-center justify-center py-4">
            <RefreshCw class="h-5 w-5 animate-spin text-gray-400" />
        </div>

        <div v-if="previewToken && !isLoading" class="space-y-3">
            <div class="flex gap-2">
                <Input :model-value="previewToken.url" readonly class="font-mono text-xs" />
                <Button type="button" size="icon" variant="outline" title="Copier le lien" @click="copyToClipboard">
                    <Copy class="h-4 w-4" />
                </Button>
                <Button type="button" size="icon" variant="outline" title="Ouvrir dans un nouvel onglet" @click="openPreview">
                    <ExternalLink class="h-4 w-4" />
                </Button>
            </div>

            <div class="flex items-center justify-between text-xs">
                <div class="flex items-center gap-1 text-muted-foreground">
                    <Clock class="h-3.5 w-3.5" />
                    <span>Expire {{ previewToken.expires_at_human }}</span>
                </div>
                <Button type="button" size="sm" variant="ghost" :disabled="isRevoking" @click="revokePreviewToken">
                    <Trash2 class="mr-1 h-3.5 w-3.5" />
                    {{ isRevoking ? 'Révocation...' : 'Révoquer' }}
                </Button>
            </div>
        </div>
    </div>
</template>
