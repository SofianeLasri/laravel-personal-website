<script setup lang="ts">
import BarStaggeredRegular from '@/components/font-awesome/BarStaggeredRegular.vue';
import BlackButton from '@/components/public/BlackButton.vue';
import NavBrand from '@/components/public/NavBrand.vue';
import NavSearchBar from '@/components/public/NavSearchBar.vue';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import NavMenuItem from '@/components/public/NavMenuItem.vue';

// État pour gérer l'ouverture/fermeture du menu
const isMenuOpen = ref(false);

// Fonction pour basculer l'état du menu
const toggleMenu = () => {
    isMenuOpen.value = !isMenuOpen.value;
};

// Fermer le menu
const closeMenu = () => {
    isMenuOpen.value = false;
};

// Gestion de la touche Escape pour fermer le menu
const handleEscKey = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && isMenuOpen.value) {
        closeMenu();
    }
};

// Bloquer le scroll quand le menu est ouvert
watch(isMenuOpen, (value) => {
    document.body.style.overflow = value ? 'hidden' : '';
});

// Mise en place des écouteurs d'événements
onMounted(() => {
    document.addEventListener('keydown', handleEscKey);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleEscKey);
    // Réinitialiser le scroll à la destruction du composant
    document.body.style.overflow = '';
});
</script>

<template>
    <div class="navbar z-10 container flex content-center justify-between py-16">
        <NavBrand />
        <div class="flex flex-nowrap items-center justify-center gap-4">
            <NavSearchBar />
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
            <div class="flex w-full max-w-lg flex-shrink-0 flex-col items-start bg-gray-100 py-16 pr-8">
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

<style scoped></style>