<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import PictureInput from '@/components/PictureInput.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem, CreationType, TranslationKey } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Créations',
        href: '#',
    },
    {
        title: 'Éditeur',
        href: route('dashboard.creations.edit', undefined, false),
    },
];

interface CreationDraftWithTranslations {
    id: number;
    name: string;
    slug: string;
    logo_id: number | null;
    cover_image_id: number | null;
    type: CreationType;
    started_at: string;
    ended_at: string | null;
    short_description_translation_key_id: number;
    full_description_translation_key_id: number;
    external_url: string | null;
    source_code_url: string | null;
    featured: boolean;
    created_at: string;
    updated_at: string;
    short_description_translation_key: TranslationKey;
    full_description_translation_key: TranslationKey;
    original_creation_id: number | null;
}

interface Props {
    creationDraft?: CreationDraftWithTranslations;
}

const props = defineProps<Props>();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Éditeur" />
        <div class="px-5 py-6">
            <Heading title="Éditeur" description="Créer ou modifier une création." />
            <div class="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="flex flex-col gap-2">
                    <Label for="creationName">Nom de la création</Label>
                    <Input id="creationName" type="text" placeholder="Nom de la création" />
                </div>
                <div class="flex flex-col gap-2">
                    <Label for="creationSlug">Slug de la création</Label>
                    <Input id="creationSlug" type="text" placeholder="Slug de la création" />
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div>
                    <div class="flex flex-col gap-2">
                        <Label for="logo">Logo</Label>
                        <PictureInput name="logo" :pictureId="props.creationDraft?.logo_id ?? undefined" />
                    </div>
                </div>
                <!--                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-2">
                        <Label for="creationSlug">Slug de la création</Label>
                        <Input id="creationSlug" type="text" placeholder="Slug de la création" />
                    </div>
                </div>-->
            </div>
        </div>
    </AppLayout>
</template>

<style scoped></style>
