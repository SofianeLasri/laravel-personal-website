<script setup lang="ts">
import Footer from '@/components/public/Footer.vue';
import Navbar from '@/components/public/Navbar/Navbar.vue';
import { SocialMediaLink } from '@/types';

interface Props {
    socialMediaLinks: SocialMediaLink[];
}

withDefaults(defineProps<Props>(), {
    socialMediaLinks: () => [],
});
</script>

<template>
    <div class="relative flex min-h-screen flex-col items-center bg-[url(/resources/images/public/dots.svg)]">
        <Navbar />
        <slot />
    </div>
    <Footer :socialMediaLinks="socialMediaLinks" />
</template>
