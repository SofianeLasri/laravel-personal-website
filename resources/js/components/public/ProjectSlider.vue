<script setup lang="ts">
import ArrowLeftRegular from '@/components/font-awesome/ArrowLeftRegular.vue';
import ArrowRightRegular from '@/components/font-awesome/ArrowRightRegular.vue';
import BlackButton from '@/components/public/Ui/Button/BlackButton.vue';
import type { SSRSimplifiedCreation } from '@/types';
import { computed, onMounted, ref, watch } from 'vue';

interface Props {
    items: SSRSimplifiedCreation[];
}

const props = defineProps<Props>();

const sliderContainer = ref<HTMLDivElement | null>(null);
const sliderContent = ref<HTMLDivElement | null>(null);
const scrollPosition = ref(0);
const containerWidth = ref(0);
const contentWidth = ref(0);
const isMobile = ref(false);

const isDragging = ref(false);
const startX = ref(0);
const startScrollPos = ref(0);

const canScrollLeft = computed(() => scrollPosition.value > 0);
const canScrollRight = computed(() => {
    return contentWidth.value > containerWidth.value && scrollPosition.value < contentWidth.value - containerWidth.value;
});

const scroll = (direction: 'left' | 'right'): void => {
    if (!sliderContent.value) return;

    const scrollAmount = containerWidth.value * 0.8;

    if (direction === 'left') {
        scrollPosition.value = Math.max(0, scrollPosition.value - scrollAmount);
    } else {
        scrollPosition.value = Math.min(contentWidth.value - containerWidth.value, scrollPosition.value + scrollAmount);
    }

    sliderContent.value.style.transform = `translateX(-${scrollPosition.value}px)`;
};

const startDrag = (e: TouchEvent): void => {
    if (!isMobile.value) return;

    isDragging.value = true;
    startX.value = e.touches[0].clientX;
    startScrollPos.value = scrollPosition.value;

    if (sliderContent.value) {
        sliderContent.value.style.transition = 'none';
    }
};

const onDrag = (e: TouchEvent): void => {
    if (!isDragging.value || !sliderContent.value || !isMobile.value) return;

    const currentX = e.touches[0].clientX;
    const diff = startX.value - currentX;
    const newScrollPos = startScrollPos.value + diff;

    scrollPosition.value = Math.max(0, Math.min(contentWidth.value - containerWidth.value, newScrollPos));

    sliderContent.value.style.transform = `translateX(-${scrollPosition.value}px)`;
};

const endDrag = (): void => {
    if (!isDragging.value || !sliderContent.value || !isMobile.value) return;

    isDragging.value = false;
    sliderContent.value.style.transition = 'transform 300ms';
};

const updateDimensions = (): void => {
    if (sliderContainer.value && sliderContent.value) {
        containerWidth.value = sliderContainer.value.offsetWidth;
        contentWidth.value = sliderContent.value.scrollWidth;

        if (contentWidth.value <= containerWidth.value) {
            scrollPosition.value = 0;
            sliderContent.value.style.transform = 'translateX(0)';
        } else if (scrollPosition.value > contentWidth.value - containerWidth.value) {
            scrollPosition.value = contentWidth.value - containerWidth.value;
            sliderContent.value.style.transform = `translateX(-${scrollPosition.value}px)`;
        }
    }
};

const checkIfMobile = (): void => {
    if (typeof window !== 'undefined') {
        isMobile.value = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    }
};

onMounted(() => {
    updateDimensions();
    checkIfMobile();

    if (typeof window !== 'undefined') {
        window.addEventListener('resize', () => {
            updateDimensions();
            checkIfMobile();
        });
    }
});

watch(
    () => props.items,
    () => {
        setTimeout(updateDimensions, 100);
    },
    { deep: true },
);
</script>

<template>
    <div class="relative overflow-hidden py-3" ref="sliderContainer">
        <button
            v-if="canScrollLeft"
            class="absolute top-0 bottom-0 left-0 z-10 flex h-full w-16 items-center justify-center bg-gradient-to-r from-gray-100 to-gray-100/0"
            aria-label="Défiler vers la gauche"
        >
            <BlackButton v-if="canScrollLeft" @click="scroll('left')" class="w-12">
                <ArrowLeftRegular class="absolute h-4 fill-white" />
            </BlackButton>
        </button>

        <div
            ref="sliderContent"
            class="flex gap-8 px-3 transition-transform duration-300 ease-in-out"
            @touchstart="startDrag"
            @touchmove="onDrag"
            @touchend="endDrag"
        >
            <slot></slot>
        </div>

        <div
            v-if="canScrollRight"
            class="absolute top-0 right-0 bottom-0 z-10 flex h-full w-16 items-center justify-center bg-gradient-to-r from-gray-100/0 to-gray-100"
        >
            <BlackButton v-if="canScrollRight" class="w-12" @click="scroll('right')">
                <ArrowRightRegular class="absolute h-4 fill-white" />
            </BlackButton>
        </div>
    </div>
</template>
