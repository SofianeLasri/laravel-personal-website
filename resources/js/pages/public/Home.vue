<script setup lang="ts">
import BarStaggeredRegular from '@/components/font-awesome/BarStaggeredRegular.vue';
import Background from '@/components/public/Background.vue';
import BlackButton from '@/components/public/BlackButton.vue';
import NavBrand from '@/components/public/NavBrand.vue';
import NavSearchBar from '@/components/public/NavSearchBar.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Accueil" />
    <div class="flex min-h-screen flex-col bg-gray-100">
        <Background />
        <div class="navbar z-1 flex content-center justify-between py-16">
            <NavBrand />
            <div class="flex flex-nowrap items-center justify-center gap-4">
                <NavSearchBar />
                <BlackButton>
                    <span>Menu</span>
                    <BarStaggeredRegular class="h-4 fill-white" />
                </BlackButton>
            </div>
        </div>
    </div>
</template>
