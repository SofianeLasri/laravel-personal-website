<script setup lang="ts">
import Navbar from '@/components/public/Navbar/Navbar.vue';
import Footer from '@/components/public/Ui/Footer.vue';
import LanguagePopup from '@/components/public/LanguagePopup.vue';
import { SocialMediaLink } from '@/types';

interface Props {
    socialMediaLinks: SocialMediaLink[];
}

withDefaults(defineProps<Props>(), {
    socialMediaLinks: () => [],
});
</script>

<template>
    <div class="dots-background relative flex min-h-screen flex-col items-center">
        <Navbar />
        <slot />
        <LanguagePopup />
    </div>
    <Footer :socialMediaLinks="socialMediaLinks" />
</template>

<style>
.dots-background {
    background-image: url('/images/dots-light.svg');
    background-repeat: repeat;
    position: relative;
}

@media (prefers-color-scheme: dark) {
    html:not(.light) .dots-background {
        background-image: url('/images/dots-dark.svg');
    }
}

html.dark .dots-background {
    background-image: url('/images/dots-dark.svg');
}

html.light .dots-background {
    background-image: url('/images/dots-light.svg');
}
</style>
