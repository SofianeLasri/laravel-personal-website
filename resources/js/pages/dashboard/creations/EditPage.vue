<script setup lang="ts">
import Heading from '@/components/Heading.vue';
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
        </div>
    </AppLayout>
</template>

<style scoped></style>
