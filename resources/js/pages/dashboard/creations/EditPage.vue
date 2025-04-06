<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import PictureInput from '@/components/PictureInput.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem, CreationDraftWithTranslations, CreationType } from '@/types';
import { creationTypeLabels, getTypeLabel } from '@/utils/creationTypes';
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

interface Props {
    creationDraft?: CreationDraftWithTranslations;
}

const props = defineProps<Props>();

const creationTypes = Object.keys(creationTypeLabels) as CreationType[];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Éditeur" />
        <div class="px-5 py-6">
            <Heading title="Éditeur" description="Créer ou modifier une création." />
            <div class="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="flex flex-col gap-2">
                    <Label for="creationName">Nom de la création</Label>
                    <Input id="creationName" type="text" placeholder="Nom de la création" :model-value="props.creationDraft?.name" />
                </div>
                <div class="flex flex-col gap-2">
                    <Label for="creationSlug">Slug de la création</Label>
                    <Input id="creationSlug" type="text" placeholder="Slug de la création" :model-value="props.creationDraft?.slug" />
                </div>
            </div>
            <div class="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-2">
                        <Label for="logo">Logo</Label>
                        <PictureInput name="logo" :pictureId="props.creationDraft?.logo_id ?? undefined" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="cover">Image de couverture</Label>
                        <PictureInput name="cover" :pictureId="props.creationDraft?.cover_image_id ?? undefined" />
                    </div>
                </div>
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-2">
                        <Label for="external_url">URL du projet (externe & publique)</Label>
                        <Input
                            id="external_url"
                            type="text"
                            placeholder="URL du projet"
                            :model-value="props.creationDraft?.external_url ?? undefined"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="source_code_url">URL du code source</Label>
                        <Input
                            id="source_code_url"
                            type="text"
                            placeholder="URL du code source"
                            :model-value="props.creationDraft?.source_code_url ?? undefined"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="type">Type de création</Label>
                        <Select :default-value="props.creationDraft?.type">
                            <SelectTrigger>
                                <SelectValue placeholder="Sélectionner un type de création" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="type in creationTypes" :key="type" :value="type">
                                    {{ getTypeLabel(type) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped></style>
