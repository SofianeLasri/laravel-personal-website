<script setup lang="ts">
import { Component, computed, onMounted, ref, watch } from 'vue';

interface PopupItem {
    id: string;
    component: Component;
    props?: Record<string, unknown>;
    shouldShow: () => boolean;
}

const props = defineProps<{
    popups: PopupItem[];
}>();

const currentIndex = ref(0);
const isVisible = ref(false);
const direction = ref<'left' | 'right'>('left');

// Filter popups that should be shown
const visiblePopups = computed(() => {
    return props.popups.filter((popup) => popup.shouldShow());
});

const hasMultiplePopups = computed(() => visiblePopups.value.length > 1);

const currentPopup = computed(() => {
    if (visiblePopups.value.length === 0) return null;
    return visiblePopups.value[currentIndex.value];
});

const progressText = computed(() => {
    if (!hasMultiplePopups.value || !currentPopup.value) return null;
    return `${currentIndex.value + 1} sur ${visiblePopups.value.length}`;
});

const goToNext = () => {
    if (currentIndex.value < visiblePopups.value.length - 1) {
        direction.value = 'left';
        setTimeout(() => {
            currentIndex.value++;
        }, 50);
    } else {
        // All popups dismissed, hide carousel
        isVisible.value = false;
    }
};

const handlePopupDismiss = () => {
    goToNext();
};

// Show carousel after delay if there are popups to show
onMounted(() => {
    if (visiblePopups.value.length > 0) {
        setTimeout(() => {
            isVisible.value = true;
        }, 2000);
    }
});

// Watch for changes in visible popups
watch(
    () => visiblePopups.value.length,
    (newLength) => {
        if (newLength === 0) {
            isVisible.value = false;
        } else if (newLength > 0 && !isVisible.value) {
            isVisible.value = true;
        }
    },
);
</script>

<template>
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="transform translate-y-full opacity-0"
        enter-to-class="transform translate-y-0 opacity-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="transform translate-y-0 opacity-100"
        leave-to-class="transform translate-y-full opacity-0"
    >
        <div
            v-if="isVisible && currentPopup"
            class="bg-action-container-outer-color action-container-outer-shadow action-container-outer-border action-container-background-blur fixed! right-4 bottom-4 z-50 w-80 rounded-3xl p-2"
        >
            <div class="action-container-inner-shadow relative overflow-hidden rounded-2xl border bg-white dark:border-gray-700 dark:bg-gray-900">
                <!-- Progress indicator -->
                <div
                    v-if="progressText"
                    class="absolute top-2 right-2 z-10 rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                >
                    {{ progressText }}
                </div>

                <!-- Popup content with slide transition -->
                <div class="relative overflow-hidden">
                    <Transition
                        mode="out-in"
                        enter-active-class="transition-transform duration-300 ease-out"
                        leave-active-class="transition-transform duration-300 ease-out"
                        :enter-from-class="`transform ${direction === 'left' ? 'translate-x-full' : '-translate-x-full'}`"
                        enter-to-class="transform translate-x-0"
                        :leave-to-class="`transform ${direction === 'left' ? '-translate-x-full' : 'translate-x-full'}`"
                    >
                        <div v-if="currentPopup" :key="currentPopup.id" class="w-full">
                            <component
                                :is="currentPopup.component"
                                v-bind="currentPopup.props"
                                @dismiss="handlePopupDismiss"
                                @close="handlePopupDismiss"
                            />
                        </div>
                    </Transition>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped></style>
