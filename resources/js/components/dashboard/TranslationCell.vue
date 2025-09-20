<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { CheckIcon, EditIcon, LanguagesIcon, XIcon } from 'lucide-vue-next';
import { ref } from 'vue';

interface Translation {
    id: number;
    locale: string;
    text: string;
}

const props = defineProps<{
    translation: Translation | null;
    locale: string;
    canTranslate?: boolean;
}>();

const emit = defineEmits<{
    save: [translation: Translation, newText: string];
    translate: [];
}>();

const isEditing = ref(false);
const editText = ref('');

function startEditing() {
    if (!props.translation) return;

    isEditing.value = true;
    editText.value = props.translation.text;
}

function cancelEdit() {
    isEditing.value = false;
    editText.value = '';
}

function saveEdit() {
    if (!props.translation || !editText.value.trim()) return;

    emit('save', props.translation, editText.value.trim());
    isEditing.value = false;
    editText.value = '';
}
</script>

<template>
    <div class="space-y-2">
        <div v-if="!isEditing && translation" class="group">
            <div class="relative">
                <div
                    class="border-border bg-muted/50 hover:bg-muted cursor-pointer rounded border p-2 text-sm whitespace-pre-wrap transition-colors"
                    @click="startEditing"
                >
                    {{ translation.text }}
                </div>
                <Button
                    variant="ghost"
                    size="sm"
                    class="absolute top-1 right-1 opacity-0 transition-opacity group-hover:opacity-100"
                    @click="startEditing"
                >
                    <EditIcon class="h-3 w-3" />
                </Button>
            </div>
        </div>

        <div v-else-if="!isEditing && !translation" class="py-4 text-center">
            <div class="text-muted-foreground mb-2 text-sm">Aucune traduction {{ locale.toUpperCase() === 'FR' ? 'française' : 'anglaise' }}</div>
            <Button v-if="canTranslate" variant="outline" size="sm" @click="$emit('translate')">
                <LanguagesIcon class="mr-2 h-4 w-4" />
                Traduire automatiquement
            </Button>
        </div>

        <div v-if="isEditing" class="space-y-2">
            <Textarea
                v-model="editText"
                :placeholder="`Saisir la traduction ${locale.toUpperCase() === 'FR' ? 'française' : 'anglaise'}...`"
                class="min-h-20 text-sm"
                @keydown.ctrl.enter="saveEdit"
                @keydown.esc="cancelEdit"
            />
            <div class="flex gap-2">
                <Button size="sm" :disabled="!editText.trim()" @click="saveEdit">
                    <CheckIcon class="mr-1 h-4 w-4" />
                    Enregistrer
                </Button>
                <Button variant="ghost" size="sm" @click="cancelEdit">
                    <XIcon class="mr-1 h-4 w-4" />
                    Annuler
                </Button>
            </div>
            <div class="text-muted-foreground text-xs">Ctrl+Entrée pour enregistrer, Échap pour annuler</div>
        </div>
    </div>
</template>
