<script setup lang="ts">
import ArrowLeftRegular from '@/components/font-awesome/ArrowLeftRegular.vue';
import ArrowRightRegular from '@/components/font-awesome/ArrowRightRegular.vue';
import LightShape from '@/components/public/LightShape.vue';
import ProjectHead from '@/components/public/ProjectPage/ProjectHead.vue';
import ProjectScreenshotsContainer from '@/components/public/ProjectPage/ProjectScreenshotsContainer.vue';
import TechnologyCard from '@/components/public/Technology/TechnologyCard.vue';
import BlackButton from '@/components/public/Ui/Button/BlackButton.vue';
import ContentSectionTitle from '@/components/public/Ui/ContentSectionTitle.vue';
import PublicAppLayout from '@/layouts/PublicAppLayout.vue';
import { SocialMediaLink, SSRFullCreation } from '@/types';
import { Head } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import VueMarkdown from 'vue-markdown-render';

const props = defineProps<{
    socialMediaLinks: SocialMediaLink[];
    creation: SSRFullCreation;
}>();

const activeSection = ref('description');
const contentContainer = ref<HTMLElement | null>(null);
const navBarRef = ref<HTMLElement | null>(null);
const navScrollContainer = ref<HTMLElement | null>(null);
const isNavSticky = ref(false);
const navHeight = ref(0);
const showLeftArrow = ref(false);
const showRightArrow = ref(false);

const sections = [{ id: 'description', label: 'Description' }];

if (props.creation.features.length > 0) {
    sections.push({ id: 'features', label: 'Fonctionnalités clés' });
}
if (props.creation.technologies.length > 0) {
    sections.push({ id: 'technologies', label: 'Technologies utilisées' });
}
if (props.creation.screenshots.length > 0) {
    sections.push({ id: 'screenshots', label: "Capture d'écrans" });
}

const scrollToSection = (sectionId: string) => {
    const section = document.getElementById(sectionId);
    if (section) {
        const offsetTop = section.offsetTop;
        const scrollToY = isNavSticky.value ? offsetTop - navHeight.value : offsetTop;

        window.scrollTo({
            top: scrollToY,
            behavior: 'smooth',
        });
        activeSection.value = sectionId;
    }
};

const checkNavArrows = () => {
    if (!navScrollContainer.value) return;

    const { scrollLeft, scrollWidth, clientWidth } = navScrollContainer.value;
    showLeftArrow.value = scrollLeft > 0;
    showRightArrow.value = scrollLeft + clientWidth < scrollWidth - 5; // Petite marge d'erreur
};

const scrollNavLeft = () => {
    if (!navScrollContainer.value) return;
    navScrollContainer.value.scrollBy({ left: -100, behavior: 'smooth' });
};

const scrollNavRight = () => {
    if (!navScrollContainer.value) return;
    navScrollContainer.value.scrollBy({ left: 100, behavior: 'smooth' });
};

const scrollToActiveButton = () => {
    if (!navScrollContainer.value) return;

    const activeButton = navScrollContainer.value.querySelector(`button[data-section="${activeSection.value}"]`);
    if (activeButton) {
        const buttonRect = activeButton.getBoundingClientRect();
        const containerRect = navScrollContainer.value.getBoundingClientRect();

        if (buttonRect.left < containerRect.left) {
            activeButton.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
        } else if (buttonRect.right > containerRect.right) {
            activeButton.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'end' });
        }
    }
};

const handleScroll = () => {
    const scrollPosition = window.scrollY + 200;
    for (const section of sections) {
        const element = document.getElementById(section.id);
        if (element) {
            const offsetTop = element.offsetTop;
            const offsetHeight = element.offsetHeight;
            if (scrollPosition >= offsetTop && scrollPosition < offsetTop + offsetHeight) {
                activeSection.value = section.id;
                break;
            }
        }
    }

    if (contentContainer.value) {
        const containerRect = contentContainer.value.getBoundingClientRect();
        const containerTop = containerRect.top;
        const containerBottom = containerRect.bottom;

        isNavSticky.value = containerTop <= 0 && containerBottom > navHeight.value;
    }

    if (isNavSticky.value) {
        checkNavArrows();
    }
};

watch(activeSection, () => {
    setTimeout(scrollToActiveButton, 50);
});

onMounted(() => {
    window.addEventListener('scroll', handleScroll);

    if (navBarRef.value) {
        navHeight.value = navBarRef.value.offsetHeight;
    }

    if (navScrollContainer.value) {
        navScrollContainer.value.addEventListener('scroll', checkNavArrows);
        checkNavArrows();
    }

    handleScroll();

    window.addEventListener('resize', () => {
        checkNavArrows();
        scrollToActiveButton();
        if (navBarRef.value) {
            navHeight.value = navBarRef.value.offsetHeight;
        }
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', handleScroll);
    if (navScrollContainer.value) {
        navScrollContainer.value.removeEventListener('scroll', checkNavArrows);
    }
    window.removeEventListener('resize', () => {});
});
</script>

<template>
    <Head title="Creation" />
    <PublicAppLayout :socialMediaLinks="socialMediaLinks">
        <div class="absolute top-0 left-0 z-0 h-full w-full overflow-hidden">
            <LightShape class="absolute top-0 left-[-27rem] xl:left-[-15rem]" />
        </div>

        <div class="z-10 container mb-16 flex flex-col gap-16 px-4">
            <ProjectHead :creation="creation" />

            <div ref="contentContainer" class="flex flex-col">
                <div
                    ref="navBarRef"
                    class="w-full border-b border-gray-200 transition-all duration-200"
                    :class="{
                        'fixed top-0 right-0 left-0 z-50 bg-gray-100 shadow-md': isNavSticky,
                        'bg-transparent': !isNavSticky,
                    }"
                >
                    <div class="relative container mx-auto px-4">
                        <BlackButton
                            v-if="showLeftArrow"
                            @click="scrollNavLeft"
                            class="absolute top-1/2 left-0 z-10 w-12 -translate-y-1/2 transition-all"
                        >
                            <ArrowLeftRegular class="absolute size-5 fill-white" />
                        </BlackButton>

                        <div ref="navScrollContainer" class="no-scrollbar flex space-x-8 overflow-x-auto">
                            <button
                                v-for="section in sections"
                                :key="section.id"
                                :data-section="section.id"
                                class="flex-shrink-0 cursor-pointer border-b-2 py-4 text-xl whitespace-nowrap transition-colors"
                                :class="
                                    activeSection === section.id ? 'border-black text-black' : 'border-transparent text-gray-500 hover:text-black'
                                "
                                @click="scrollToSection(section.id)"
                            >
                                {{ section.label }}
                            </button>
                        </div>

                        <BlackButton
                            v-if="showRightArrow"
                            @click="scrollNavRight"
                            class="absolute top-1/2 right-0 z-10 w-12 -translate-y-1/2 transition-all"
                        >
                            <ArrowRightRegular class="absolute size-5 fill-white" />
                        </BlackButton>
                    </div>
                </div>

                <div v-if="isNavSticky" :style="{ height: `${navHeight}px` }"></div>

                <div class="content-sections mt-8">
                    <section id="description" class="flex flex-col gap-8">
                        <ContentSectionTitle>Description</ContentSectionTitle>
                        <vue-markdown class="markdown-view" :source="creation.fullDescription" />
                    </section>

                    <section id="features" class="mt-16 flex flex-col gap-8" v-if="creation.features.length > 0">
                        <ContentSectionTitle>Fonctionnalités clés</ContentSectionTitle>
                        <div class="grid gap-16 md:grid-cols-2 lg:grid-cols-3">
                            <div v-for="feature in creation.features" :key="feature.id" class="flex flex-col gap-6">
                                <h3 class="text-design-system-paragraph text-xl font-bold">{{ feature.title }}</h3>
                                <div class="text-design-system-paragraph text-lg font-normal">{{ feature.description }}</div>
                            </div>
                        </div>
                    </section>

                    <section id="technologies" class="mt-16 flex flex-col gap-8" v-if="creation.technologies.length > 0">
                        <ContentSectionTitle>Technologies utilisées</ContentSectionTitle>
                        <div class="grid grid-cols-1 gap-3 self-stretch sm:grid-cols-2 lg:gap-4 xl:grid-cols-3">
                            <TechnologyCard
                                v-for="tech in creation.technologies"
                                :key="tech.name"
                                :name="tech.name"
                                :description="tech.description"
                                :svgIcon="tech.svgIcon"
                                class="bg-gray-100"
                            />
                        </div>
                    </section>

                    <section id="screenshots" class="mt-16 flex flex-col gap-8" v-if="creation.screenshots.length > 0">
                        <ContentSectionTitle>Captures d'écrans</ContentSectionTitle>
                        <ProjectScreenshotsContainer :screenshots="creation.screenshots" />
                    </section>
                </div>
            </div>
        </div>
    </PublicAppLayout>
</template>

<style scoped>
.no-scrollbar {
    scrollbar-width: none; /* Pour Firefox */
    -webkit-overflow-scrolling: touch; /* Défilement fluide sur iOS */
}

.no-scrollbar::-webkit-scrollbar {
    display: none;
}
</style>
