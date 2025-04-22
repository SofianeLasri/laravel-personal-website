<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Background from '@/components/public/Background.vue';
import Logo from '@/components/public/Logo.vue';
</script>

<template>
    <Head title="Accueil" />
    <div class="flex min-h-screen flex-col bg-gray-100">
        <Background />
        <div class="navbar z-1 flex content-center justify-between py-16">
            <div class="flex items-center gap-4">
                <Logo />
                <div class="flex flex-col">
                    <div class="text-xl leading-5 font-bold">Sofiane Lasri.</div>
                    <div class="text-design-system-paragraph-color text-sm leading-3.5">Développeur Full-Stack</div>
                </div>
            </div>
        </div>
    </div>
</template>