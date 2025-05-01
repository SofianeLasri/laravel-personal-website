<script setup lang="ts">
import PublicAppLayout from '@/layouts/PublicAppLayout.vue';
import { SocialMediaLink } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{
    socialMediaLinks: SocialMediaLink[];
}>();
</script>

<template>
    <Head title="Projects" />
    <PublicAppLayout :socialMediaLinks="socialMediaLinks"> </PublicAppLayout>
</template>
