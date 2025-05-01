<script setup lang="ts">
import Logo from '@/components/public/Logo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <Link class="flex flex-shrink-0 items-center gap-4" :href="route('home')">
        <Logo />
        <div class="xs:flex hidden flex-col">
            <div class="text-xl leading-5 font-bold">Sofiane Lasri.</div>
            <div class="text-design-system-paragraph-color text-sm leading-3.5">Développeur Full-Stack</div>
        </div>
    </Link>
</template>
