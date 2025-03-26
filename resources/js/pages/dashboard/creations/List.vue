<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';

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
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="list" />
        <div class="px-5 py-6">
            <Heading
                title="Liste des créations"
                description="Sont affichées ici uniquement les créations publiées." />
        </div>
    </AppLayout>
</template>
