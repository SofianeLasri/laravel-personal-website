<script setup lang="ts">
import BarStaggeredRegular from '@/components/font-awesome/BarStaggeredRegular.vue';
import BlackButton from '@/components/public/BlackButton.vue';
import NavBrand from '@/components/public/NavBrand.vue';
import NavMenuItem from '@/components/public/NavMenuItem.vue';
import NavSearchBar from '@/components/public/NavSearchBar.vue';
import { onMounted, onUnmounted, ref, watch } from 'vue';

const isMenuOpen = ref(false);

const toggleMenu = () => {
    isMenuOpen.value = !isMenuOpen.value;
};

const closeMenu = () => {
    isMenuOpen.value = false;
};

const handleEscKey = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && isMenuOpen.value) {
        closeMenu();
    }
};

watch(isMenuOpen, (value) => {
    document.body.style.overflow = value ? 'hidden' : '';
});

onMounted(() => {
    document.addEventListener('keydown', handleEscKey);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleEscKey);
    document.body.style.overflow = '';
});
</script>

<template>
    <div class="navbar z-10 container flex content-center justify-between py-16">
        <NavBrand />
        <div class="flex flex-nowrap items-center justify-center gap-4">
            <NavSearchBar class="hidden md:flex" />
            <BlackButton @click="toggleMenu" :aria-expanded="isMenuOpen" aria-controls="fullscreen-menu">
                <span>Menu</span>
                <BarStaggeredRegular class="h-4 fill-white" />
            </BlackButton>
        </div>
    </div>

    <!-- Menu plein écran avec transition -->
    <Transition name="menu">
        <div
            v-if="isMenuOpen"
            id="fullscreen-menu"
            class="fixed inset-0 z-50 flex bg-gray-200/25 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            aria-label="Menu principal"
        >
            <div class="flex-grow-1 cursor-pointer" @click="closeMenu"></div>
            <div class="bg-background motion-preset-slide-left flex w-full max-w-lg flex-shrink-0 flex-col items-start border-l py-16 pr-8">
                <div class="flex w-full flex-col gap-12">
                    <BlackButton
                        class="self-end"
                        @click="closeMenu"
                        :aria-expanded="isMenuOpen"
                        aria-controls="fullscreen-menu"
                        aria-label="Fermer le menu"
                    >
                        <span>Femer</span>
                        <BarStaggeredRegular class="h-4 fill-white" />
                    </BlackButton>

                    <div class="flex flex-col gap-8">
                        <div class="pl-12">
                            <h2 class="text-4xl font-bold">Portfolio.</h2>
                        </div>
                        <div class="flex flex-col gap-3">
                            <NavMenuItem text="Accueil" :active="true" />
                            <NavMenuItem text="Projets" :active="false" />
                            <NavMenuItem text="Parcours professionnel & scolaire" :active="false" />
                            <NavMenuItem text="À propos" :active="false" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>
