<script setup lang="ts">
import LanguagePopup from '@/components/public/LanguagePopup.vue';
import Navbar from '@/components/public/Navbar/Navbar.vue';
import Footer from '@/components/public/Ui/Footer.vue';
import DotMatrixMask from '@/components/public/DotMatrixMask.vue';
import { SocialMediaLink } from '@/types';

interface Props {
    socialMediaLinks: SocialMediaLink[];
}

withDefaults(defineProps<Props>(), {
    socialMediaLinks: () => [],
});
</script>

<template>
    <div class="dots-background dark:bg-gray-990 relative flex min-h-screen flex-col items-center">
        <DotMatrixMask />
        <Navbar />
        <slot />
        <LanguagePopup />
    </div>
    <Footer :socialMediaLinks="socialMediaLinks" />
</template>

<style>
.dots-background {
    position: relative;
}
</style>
