<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Créations',
        href: '#',
    },
    {
        title: 'Liste des créations',
        href: route('dashboard.creations.index', undefined, false),
    },
];

interface Translation {
    id: number;
    translation_key_id: number;
    locale: string;
    text: string;
}

interface TranslationKey {
    id: number;
    key: string;
    translations: Translation[];
}

type CreationType = 'portfolio' | 'game' | 'library' | 'website' | 'tool' | 'map' | 'other';

interface CreationWithTranslations {
    id: number;
    name: string;
    slug: string;
    logo_id: number;
    cover_image_id: number;
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
}

interface Props {
    creations: CreationWithTranslations[];
}

const props = defineProps<Props>();

console.log(props.creations);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="list" />
        <div class="px-5 py-6">
            <Heading title="Liste des créations" description="Sont affichées ici uniquement les créations publiées." />
        </div>
    </AppLayout>
</template>
