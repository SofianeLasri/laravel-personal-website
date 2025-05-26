<script setup lang="ts">
import PublicAppLayout from '@/layouts/PublicAppLayout.vue';
import { SocialMediaLink } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    socialMediaLinks: SocialMediaLink[];
}>();
</script>

<template>
    <Head title="Certifications & Parcours" />
    <PublicAppLayout :socialMediaLinks="socialMediaLinks"> </PublicAppLayout>
</template>

<style scoped></style>
