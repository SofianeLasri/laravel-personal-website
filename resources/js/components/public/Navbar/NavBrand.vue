<script setup lang="ts">
import Logo from '@/components/public/Logo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <Link class="flex flex-shrink-0 items-center gap-4" :href="route('public.home')">
        <Logo />
        <div class="flex flex-col">
            <div class="text-xl leading-5 font-bold text-black dark:text-gray-100">Sofiane Lasri.</div>
            <div class="text-design-system-paragraph text-sm leading-3.5">Développeur Full-Stack</div>
        </div>
    </Link>
</template>
