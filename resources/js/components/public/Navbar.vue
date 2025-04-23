<script setup lang="ts">
import BarStaggeredRegular from '@/components/font-awesome/BarStaggeredRegular.vue';
import BlackButton from '@/components/public/BlackButton.vue';
import NavBrand from '@/components/public/NavBrand.vue';
import NavSearchBar from '@/components/public/NavSearchBar.vue';
</script>

<template>
    <div class="navbar z-1 container flex content-center justify-between py-16">
        <NavBrand />
        <div class="flex flex-nowrap items-center justify-center gap-4">
            <NavSearchBar />
            <BlackButton>
                <span>Menu</span>
                <BarStaggeredRegular class="h-4 fill-white" />
            </BlackButton>
        </div>
    </div>
</template>
