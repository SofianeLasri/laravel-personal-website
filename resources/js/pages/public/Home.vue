<script setup lang="ts">
import Background from '@/components/public/Background.vue';
import Navbar from '@/components/public/Navbar.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Accueil" />
    <div class="flex min-h-screen flex-col items-center bg-gray-100 px-2">
        <Background />
        <Navbar />
    </div>
</template>
