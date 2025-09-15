<script setup lang="ts">
import BorderGlow from '@/components/public/BorderGlow.vue';
import DotMatrixMask from '@/components/public/DotMatrixMask.vue';
import LanguagePopup from '@/components/public/LanguagePopup.vue';
import Navbar from '@/components/public/Navbar/Navbar.vue';
import Footer from '@/components/public/Ui/Footer.vue';
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
        <BorderGlow />
        <Navbar />
        <slot />
        <LanguagePopup />
    </div>
    <Footer :social-media-links="socialMediaLinks" />
</template>

<style>
.dots-background {
    position: relative;
}
</style>
