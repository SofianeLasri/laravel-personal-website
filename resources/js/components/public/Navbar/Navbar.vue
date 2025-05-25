<script setup lang="ts">
import BarStaggeredRegular from '@/components/font-awesome/BarStaggeredRegular.vue';
import MagnifyingGlassRegular from '@/components/font-awesome/MagnifyingGlassRegular.vue';
import NavBrand from '@/components/public/Navbar/NavBrand.vue';
import NavMenuItem from '@/components/public/Navbar/NavMenuItem.vue';
import NavSearchBar from '@/components/public/Navbar/NavSearchBar.vue';
import SearchModal from '@/components/public/SearchModal.vue';
import BlackButton from '@/components/public/Ui/Button/BlackButton.vue';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const page = usePage();
const currentUrl = computed(() => new URL(page.props.ziggy.location));
const currentPath = computed(() => currentUrl.value.href);

const isMenuOpen = ref(false);
const isSearchModalOpen = ref(false);
const hoveredItemIndex = ref(null);
const indicatorPosition = ref(0);
const indicatorVisible = ref(false);
const linkHeight = ref(48);
const linkGap = 12;

const routes = [
    { path: route('public.home'), name: 'Accueil', index: 0 },
    { path: route('public.projects'), name: 'Projets', index: 1 },
    /*{ path: '#', name: 'Parcours professionnel & scolaire', index: 2 },*/
    { path: '#', name: 'À propos', index: 2 },
];

const activeIndex = computed(() => {
    const cleanedPath = currentPath.value.endsWith('/') ? currentPath.value.slice(0, -1) : currentPath.value;
    const matchingRoute = routes.find((r) => r.path === cleanedPath);
    return matchingRoute ? matchingRoute.index : null;
});

const isItemActive = (index: any) => {
    return index === activeIndex.value;
};

const toggleMenu = () => {
    isMenuOpen.value = !isMenuOpen.value;
};

const closeMenu = () => {
    isMenuOpen.value = false;
};

const openSearchModal = () => {
    isSearchModalOpen.value = true;
    closeMenu();
};

const closeSearchModal = () => {
    isSearchModalOpen.value = false;
};

const handleEscKey = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && isMenuOpen.value) {
        closeMenu();
    }
};

const updateIndicatorPosition = (index: any) => {
    hoveredItemIndex.value = index;
    indicatorVisible.value = true;

    if (index === 0) {
        indicatorPosition.value = 0;
    } else {
        indicatorPosition.value = index * (linkHeight.value + linkGap);
    }
};

const resetIndicator = () => {
    hoveredItemIndex.value = null;

    if (activeIndex.value !== null) {
        indicatorPosition.value = activeIndex.value * (linkHeight.value + linkGap);
        indicatorVisible.value = true;
    } else {
        indicatorVisible.value = false;
    }
};

watch(isMenuOpen, (value) => {
    document.body.style.overflow = value ? 'hidden' : '';
    if (value) {
        if (activeIndex.value !== null) {
            indicatorPosition.value = activeIndex.value * (linkHeight.value + linkGap);
            indicatorVisible.value = true;
        } else {
            indicatorPosition.value = 0;
            indicatorVisible.value = false;
        }
    }
});

onMounted(() => {
    document.addEventListener('keydown', handleEscKey);

    if (activeIndex.value !== null) {
        indicatorPosition.value = activeIndex.value * (linkHeight.value + linkGap);
        indicatorVisible.value = true;
    } else {
        indicatorPosition.value = 0;
        indicatorVisible.value = false;
    }
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleEscKey);
    document.body.style.overflow = '';
});
</script>

<template>
    <div class="navbar z-10 container flex content-center justify-between px-4 py-16">
        <NavBrand />
        <div class="flex flex-nowrap items-center justify-center gap-4">
            <NavSearchBar class="hidden md:flex" @click="openSearchModal" />
            <BlackButton @click="toggleMenu" :aria-expanded="isMenuOpen" aria-controls="fullscreen-menu" class="xs:w-auto w-12">
                <span class="xs:block hidden">Menu</span>
                <BarStaggeredRegular class="xs:relative absolute size-4 fill-white" />
            </BlackButton>
        </div>
    </div>

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
                    <div class="flex items-center justify-end gap-4">
                        <BlackButton class="md:hidden" @click="openSearchModal" aria-label="Ouvrir la recherche">
                            <span>Rechercher</span>
                            <MagnifyingGlassRegular class="size-4 fill-white" />
                        </BlackButton>
                        <BlackButton @click="closeMenu" :aria-expanded="isMenuOpen" aria-controls="fullscreen-menu" aria-label="Fermer le menu">
                            <span>Fermer</span>
                            <BarStaggeredRegular class="size-4 fill-white" />
                        </BlackButton>
                    </div>

                    <div class="flex flex-col gap-8">
                        <div class="pl-12">
                            <h2 class="text-4xl font-bold">Portfolio.</h2>
                        </div>
                        <div class="relative flex flex-col gap-3">
                            <div
                                class="bg-primary absolute left-0 h-12 w-1 transition-all duration-300 ease-in-out"
                                :style="{
                                    transform: `translateY(${indicatorPosition}px)`,
                                    opacity: indicatorVisible ? 1 : 0,
                                }"
                            ></div>

                            <div
                                v-for="(item, index) in routes"
                                :key="index"
                                @mouseenter="updateIndicatorPosition(index)"
                                @mouseleave="resetIndicator"
                            >
                                <NavMenuItem :text="item.name" :active="isItemActive(index)" :to="item.path" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Transition>

    <!-- Search Modal -->
    <SearchModal :isOpen="isSearchModalOpen" @close="closeSearchModal" />
</template>
