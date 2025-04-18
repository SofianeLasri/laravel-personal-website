<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Picture } from '@/types';
import { useVModel } from '@vueuse/core';
import axios from 'axios';
import { Image } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    modelValue?: number;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
}>();

const modelValue = useVModel(props, 'modelValue', emit, {
    passive: true,
});

const picture = ref<Picture | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);

const imageUrl = computed(() => {
    if (picture.value) {
        return '/storage/' + picture.value.path_original;
    }
    return null;
});

const loadPicture = async (id: number) => {
    if (!id) return;

    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get(route('dashboard.api.pictures.show', { picture: id }));
        picture.value = response.data;
    } catch (err) {
        error.value = "Impossible de charger l'image";
        console.error(err);
        picture.value = null;
        emit('update:modelValue', null);
    } finally {
        loading.value = false;
    }
};

const uploadPicture = async (file: File) => {
    loading.value = true;
    error.value = null;

    try {
        const formData = new FormData();
        formData.append('picture', file);

        const response = await axios.post(route('dashboard.api.pictures.index'), formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        picture.value = response.data;
        emit('update:modelValue', response.data.id);
    } catch (err) {
        error.value = "Impossible d'envoyer l'image";
        console.error(err);
    } finally {
        loading.value = false;
    }
};

const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        const file = target.files[0];
        uploadPicture(file);
    }
};

const removePicture = () => {
    picture.value = null;
    emit('update:modelValue', null);
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const triggerFileInput = () => {
    if (fileInput.value) {
        fileInput.value.click();
    }
};

onMounted(() => {
    const idToLoad = props.modelValue;
    if (idToLoad) {
        loadPicture(idToLoad);
    }
});

watch([() => props.modelValue], ([newModelValue]) => {
    const newId = newModelValue;

    if (newId !== picture.value?.id) {
        if (newId) {
            loadPicture(newId);
        } else {
            picture.value = null;
        }
    }
});
</script>

<template>
    <div class="flex items-center gap-4">
        <!-- Prévisualisation de l'image -->
        <div class="border-input bg-muted relative flex h-20 w-20 items-center justify-center overflow-hidden rounded-md border">
            <img v-if="imageUrl" :src="imageUrl" :alt="picture?.filename || 'Image'" class="h-full w-full object-cover" />
            <Image v-else class="text-muted-foreground h-8 w-8" />
            <div v-if="loading" class="bg-muted/75 absolute inset-0 flex items-center justify-center">
                <div class="border-primary h-6 w-6 animate-spin rounded-full border-b-2"></div>
            </div>
        </div>

        <!-- Informations et actions -->
        <div class="flex flex-col">
            <p class="mb-2 text-sm">
                <span v-if="picture">{{ picture.filename }}</span>
                <span v-else class="text-muted-foreground">Aucune image sélectionnée</span>
            </p>

            <div class="flex gap-2">
                <Button v-if="picture" variant="destructive" size="sm" @click="removePicture" :disabled="loading"> Supprimer </Button>
                <Button v-else variant="outline" size="sm" @click="triggerFileInput" :disabled="loading">Ajouter </Button>
            </div>

            <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="handleFileChange" />

            <input type="hidden" v-model="modelValue" />

            <p v-if="error" class="text-destructive mt-1 text-xs">{{ error }}</p>
        </div>
    </div>
</template>
