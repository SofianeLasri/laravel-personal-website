<script setup lang="ts">
import ArrowLeftRegular from '@/components/font-awesome/ArrowLeftRegular.vue';
import ArrowRightRegular from '@/components/font-awesome/ArrowRightRegular.vue';
import BaseButton from '@/components/public/Ui/Button/BaseButton.vue';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface NavItem {
    id: string;
    label: string;
}

const props = defineProps<{
    items: NavItem[];
    activeItem?: string;
    // 'auto' = comme sur page projet (détection auto de section active)
    // 'manual' = comme sur page projets (changement uniquement au clic)
    mode?: 'auto' | 'manual';
    sticky?: boolean;
    showArrows?: boolean;
    containerRef?: HTMLElement | null;
}>();

const emit = defineEmits<{
    (e: 'update:activeItem', id: string): void;
    (e: 'item-click', id: string): void;
}>();

const currentActiveItem = ref(props.activeItem || (props.items.length > 0 ? props.items[0].id : ''));
const navBarRef = ref<HTMLElement | null>(null);
const navScrollContainer = ref<HTMLElement | null>(null);
const isNavSticky = ref(false);
const navHeight = ref(0);
const showLeftArrow = ref(false);
const showRightArrow = ref(false);

const navigateToItem = (itemId: string) => {
    emit('item-click', itemId);

    if (props.mode === 'auto') {
        const section = document.getElementById(itemId);
        if (section) {
            const offsetTop = section.offsetTop;
            const scrollToY = isNavSticky.value ? offsetTop - navHeight.value : offsetTop;

            if (typeof window !== 'undefined') {
                window.scrollTo({
                    top: scrollToY,
                    behavior: 'smooth',
                });
            }
        }
    }

    currentActiveItem.value = itemId;
    emit('update:activeItem', itemId);
};

const checkNavArrows = () => {
    if (!navScrollContainer.value || !props.showArrows) return;

    const { scrollLeft, scrollWidth, clientWidth } = navScrollContainer.value;
    showLeftArrow.value = scrollLeft > 0;
    showRightArrow.value = scrollLeft + clientWidth < scrollWidth - 5;
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

    const activeButton = navScrollContainer.value.querySelector(`button[data-item="${currentActiveItem.value}"]`);
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
    if (props.mode === 'auto' && typeof window !== 'undefined') {
        const scrollPosition = window.scrollY + 200;
        for (const item of props.items) {
            const element = document.getElementById(item.id);
            if (element) {
                const offsetTop = element.offsetTop;
                const offsetHeight = element.offsetHeight;
                if (scrollPosition >= offsetTop && scrollPosition < offsetTop + offsetHeight) {
                    if (currentActiveItem.value !== item.id) {
                        currentActiveItem.value = item.id;
                        emit('update:activeItem', item.id);
                    }
                    break;
                }
            }
        }
    }

    if (props.sticky) {
        const container = props.containerRef;
        if (container) {
            const containerRect = container.getBoundingClientRect();
            const containerTop = containerRect.top;
            const containerBottom = containerRect.bottom;

            isNavSticky.value = containerTop <= 0 && containerBottom > navHeight.value;
        }

        if (isNavSticky.value && props.showArrows) {
            checkNavArrows();
        }
    }
};

watch(currentActiveItem, () => {
    if (props.showArrows) {
        setTimeout(scrollToActiveButton, 50);
    }
});

watch(
    () => props.activeItem,
    (newValue) => {
        if (newValue !== undefined && newValue !== currentActiveItem.value) {
            currentActiveItem.value = newValue;
        }
    },
);

onMounted(() => {
    if (typeof window !== 'undefined') {
        if (props.mode === 'auto' || props.sticky) {
            window.addEventListener('scroll', handleScroll);
        }

        if (navBarRef.value) {
            navHeight.value = navBarRef.value.offsetHeight;
        }

        if (props.showArrows && navScrollContainer.value) {
            navScrollContainer.value.addEventListener('scroll', checkNavArrows);
            checkNavArrows();
        }

        if (props.mode === 'auto' || props.sticky) {
            handleScroll();
        }

        window.addEventListener('resize', () => {
            if (props.showArrows) {
                checkNavArrows();
                scrollToActiveButton();
            }
            if (navBarRef.value) {
                navHeight.value = navBarRef.value.offsetHeight;
            }
        });
    }
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        if (props.mode === 'auto' || props.sticky) {
            window.removeEventListener('scroll', handleScroll);
        }
        if (props.showArrows && navScrollContainer.value) {
            navScrollContainer.value.removeEventListener('scroll', checkNavArrows);
        }
        window.removeEventListener('resize', () => {});
    }
});
</script>

<template>
    <div
        ref="navBarRef"
        class="w-full border-b border-gray-200 transition-all duration-200 dark:border-gray-800"
        :class="{
            'fixed top-0 right-0 left-0 z-50 bg-gray-100 shadow-md dark:bg-gray-900': isNavSticky && sticky,
            'bg-transparent': !(isNavSticky && sticky),
        }"
    >
        <div class="relative container mx-auto px-4">
            <BaseButton
                variant="black"
                v-if="showArrows && showLeftArrow"
                @click="scrollNavLeft"
                class="absolute top-1/2 left-0 z-10 w-12 -translate-y-1/2 transition-all"
            >
                <ArrowLeftRegular class="dark:fill-gray-990 absolute size-5 fill-white" />
            </BaseButton>

            <div ref="navScrollContainer" class="no-scrollbar flex space-x-8 overflow-x-auto" data-testid="section-nav">
                <button
                    v-for="item in items"
                    :key="item.id"
                    :data-item="item.id"
                    :data-tab="item.id"
                    :data-section="item.id"
                    :data-active="currentActiveItem === item.id"
                    class="no-glow flex-shrink-0 cursor-pointer border-b-2 py-4 text-xl whitespace-nowrap transition-colors"
                    :class="
                        currentActiveItem === item.id
                            ? 'border-black text-black dark:border-gray-100 dark:text-gray-100'
                            : 'border-transparent text-gray-500 hover:text-black dark:text-gray-400 dark:hover:text-gray-100'
                    "
                    @click="navigateToItem(item.id)"
                >
                    {{ item.label }}
                </button>
            </div>

            <BaseButton
                variant="black"
                v-if="showArrows && showRightArrow"
                @click="scrollNavRight"
                class="absolute top-1/2 right-0 z-10 w-12 -translate-y-1/2 transition-all"
            >
                <ArrowRightRegular class="dark:fill-gray-990 absolute size-5 fill-white" />
            </BaseButton>
        </div>
    </div>
    <div v-if="isNavSticky && sticky" :style="{ height: `${navHeight}px` }"></div>
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
