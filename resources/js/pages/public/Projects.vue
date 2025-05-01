<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PublicAppLayout from '@/layouts/PublicAppLayout.vue';
import { SocialMediaLink } from '@/types';

defineProps<{
    socialMediaLinks: SocialMediaLink[];
}>();
</script>

<template>
    <Head title="Projects" />
    <PublicAppLayout :socialMediaLinks="socialMediaLinks"> </PublicAppLayout>
</template>
