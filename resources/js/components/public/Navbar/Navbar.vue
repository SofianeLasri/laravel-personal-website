<script setup lang="ts">
import BarStaggeredRegular from '@/components/font-awesome/BarStaggeredRegular.vue';
import MagnifyingGlassRegular from '@/components/font-awesome/MagnifyingGlassRegular.vue';
import LanguageToggle from '@/components/public/LanguageToggle.vue';
import NavBrand from '@/components/public/Navbar/NavBrand.vue';
import NavMenuItem from '@/components/public/Navbar/NavMenuItem.vue';
import NavSearchBar from '@/components/public/Navbar/NavSearchBar.vue';
import SearchModal from '@/components/public/SearchModal.vue';
import ThemeToggle from '@/components/public/ThemeToggle.vue';
import BaseButton from '@/components/public/Ui/Button/BaseButton.vue';
import { useRoute } from '@/composables/useRoute';
import { useTranslation } from '@/composables/useTranslation';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const page = usePage();
const route = useRoute();
const { t } = useTranslation();
const currentUrl = computed(() => new URL(page.props.ziggy.location));
const currentPath = computed(() => currentUrl.value.href);
const hasBlogPosts = computed(() => page.props.hasBlogPosts as boolean);

const isMenuOpen = ref(false);
const isSearchModalOpen = ref(false);
const hoveredItemIndex = ref<number | null>(null);
const indicatorPosition = ref(0);
const indicatorVisible = ref(false);
const linkHeight = ref(48);
const linkGap = 12;

const portfolioRoutes = [
    { path: route('public.home'), name: t('navigation.home'), index: 0 },
    { path: route('public.projects'), name: t('navigation.projects'), index: 1 },
    { path: route('public.certifications-career'), name: t('navigation.certifications-career'), index: 2 },
    { path: route('public.about'), name: t('navigation.about'), index: 3 },
];

const blogRoutes = hasBlogPosts.value
    ? [
          { path: route('public.blog.home'), name: t('navigation.blog_home'), index: 4 },
          { path: route('public.blog.index'), name: t('navigation.blog_all_articles'), index: 5 },
      ]
    : [];

const allRoutes = [...portfolioRoutes, ...blogRoutes];

const activeIndex = computed(() => {
    const cleanedPath = currentPath.value.endsWith('/') ? currentPath.value.slice(0, -1) : currentPath.value;
    const matchingRoute = allRoutes.find((r) => r.path === cleanedPath);
    return matchingRoute ? matchingRoute.index : null;
});

const activeSection = computed(() => {
    if (activeIndex.value === null) return null;
    return activeIndex.value <= 3 ? 'portfolio' : 'blog';
});

const getRelativeIndex = (absoluteIndex: number) => {
    if (absoluteIndex <= 3) return absoluteIndex;
    return absoluteIndex - 4;
};

const isItemActive = (index: number | null) => {
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

const updateIndicatorPosition = (index: number) => {
    hoveredItemIndex.value = index;
    indicatorVisible.value = true;

    const relativeIndex = getRelativeIndex(index);
    if (relativeIndex === 0) {
        indicatorPosition.value = 0;
    } else {
        indicatorPosition.value = relativeIndex * (linkHeight.value + linkGap);
    }
};

const resetIndicator = () => {
    hoveredItemIndex.value = null;

    if (activeIndex.value !== null) {
        const relativeIndex = getRelativeIndex(activeIndex.value);
        if (relativeIndex === 0) {
            indicatorPosition.value = 0;
        } else {
            indicatorPosition.value = relativeIndex * (linkHeight.value + linkGap);
        }
        indicatorVisible.value = true;
    } else {
        indicatorVisible.value = false;
    }
};

watch(isMenuOpen, (value) => {
    document.body.style.overflow = value ? 'hidden' : '';
    if (value) {
        if (activeIndex.value !== null) {
            const relativeIndex = getRelativeIndex(activeIndex.value);
            if (relativeIndex === 0) {
                indicatorPosition.value = 0;
            } else {
                indicatorPosition.value = relativeIndex * (linkHeight.value + linkGap);
            }
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
        const relativeIndex = getRelativeIndex(activeIndex.value);
        if (relativeIndex === 0) {
            indicatorPosition.value = 0;
        } else {
            indicatorPosition.value = relativeIndex * (linkHeight.value + linkGap);
        }
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
            <NavSearchBar class="hidden md:flex" :placeholder="t('navigation.search_placeholder')" @click="openSearchModal" />
            <BaseButton variant="black" :aria-expanded="isMenuOpen" aria-controls="fullscreen-menu" class="xs:w-auto w-12" @click="toggleMenu">
                <span class="xs:block hidden">{{ t('navigation.menu') }}</span>
                <BarStaggeredRegular class="xs:relative dark:fill-gray-990 absolute size-4 fill-white" />
            </BaseButton>
        </div>
    </div>

    <Transition name="menu">
        <div
            v-if="isMenuOpen"
            id="fullscreen-menu"
            class="fixed inset-0 z-50 flex bg-gray-200/25 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            :aria-label="t('navigation.main_menu')"
        >
            <div class="flex-grow-1 cursor-pointer" @click="closeMenu"></div>
            <div
                class="bg-background motion-preset-slide-left flex h-screen w-full max-w-lg flex-shrink-0 flex-col items-start overflow-y-auto border-l py-16 pr-8 dark:border-gray-800 dark:bg-gray-900"
            >
                <div class="flex w-full flex-col gap-12">
                    <div class="flex items-center justify-end gap-4">
                        <BaseButton variant="black" class="md:hidden" :aria-label="t('navigation.open_search')" @click="openSearchModal">
                            <span>{{ t('navigation.search') }}</span>
                            <MagnifyingGlassRegular class="dark:fill-gray-990 size-4 fill-white" />
                        </BaseButton>
                        <BaseButton
                            variant="black"
                            :aria-expanded="isMenuOpen"
                            aria-controls="fullscreen-menu"
                            :aria-label="t('navigation.close_menu')"
                            @click="closeMenu"
                        >
                            <span>{{ t('navigation.close') }}</span>
                            <BarStaggeredRegular class="dark:fill-gray-990 size-4 fill-white" />
                        </BaseButton>
                    </div>

                    <div class="flex flex-col gap-8">
                        <!-- Portfolio Section -->
                        <div class="pl-12">
                            <h2 class="text-4xl font-bold dark:text-white">{{ t('navigation.portfolio') }}</h2>
                        </div>
                        <div class="relative flex flex-col gap-3">
                            <div
                                v-if="activeSection === 'portfolio'"
                                class="bg-primary dark:bg-primary-400 absolute left-0 h-12 w-1 transition-all duration-300 ease-in-out"
                                :style="{
                                    transform: `translateY(${indicatorPosition}px)`,
                                    opacity: indicatorVisible ? 1 : 0,
                                }"
                            ></div>

                            <div
                                v-for="(item, index) in portfolioRoutes"
                                :key="`portfolio-${index}`"
                                @mouseenter="updateIndicatorPosition(item.index)"
                                @mouseleave="resetIndicator"
                            >
                                <NavMenuItem :text="item.name" :active="isItemActive(item.index)" :to="item.path" />
                            </div>
                        </div>

                        <!-- Blog Section -->
                        <template v-if="hasBlogPosts">
                            <div class="pl-12">
                                <h2 class="text-4xl font-bold dark:text-white">{{ t('navigation.blog') }}</h2>
                            </div>
                            <div class="relative flex flex-col gap-3">
                                <div
                                    v-if="activeSection === 'blog'"
                                    class="bg-primary dark:bg-primary-400 absolute left-0 h-12 w-1 transition-all duration-300 ease-in-out"
                                    :style="{
                                        transform: `translateY(${indicatorPosition}px)`,
                                        opacity: indicatorVisible ? 1 : 0,
                                    }"
                                ></div>

                                <div
                                    v-for="(item, index) in blogRoutes"
                                    :key="`blog-${index}`"
                                    @mouseenter="updateIndicatorPosition(item.index)"
                                    @mouseleave="resetIndicator"
                                >
                                    <NavMenuItem :text="item.name" :active="isItemActive(item.index)" :to="item.path" />
                                </div>
                            </div>
                        </template>

                        <!-- Theme and Language Section -->
                        <div class="mt-8 pl-12">
                            <div class="flex flex-col gap-4">
                                <div class="flex items-center gap-4">
                                    <span class="text-lg text-gray-600 dark:text-gray-400">{{ t('navigation.theme') }}</span>
                                    <ThemeToggle />
                                </div>
                                <div class="flex items-center gap-4">
                                    <span class="text-lg text-gray-600 dark:text-gray-400">{{ t('navigation.language') }}</span>
                                    <LanguageToggle />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Transition>

    <!-- Search Modal -->
    <SearchModal :is-open="isSearchModalOpen" @close="closeSearchModal" />
</template>
