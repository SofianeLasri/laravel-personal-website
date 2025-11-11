<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

export interface EmojiSuggestionItem {
    type: 'native' | 'custom';
    emoji?: string; // For native emojis
    name: string;
    preview_url?: string; // For custom emojis
}

interface Props {
    items: EmojiSuggestionItem[];
    command: (item: EmojiSuggestionItem) => void;
}

const props = defineProps<Props>();

const selectedIndex = ref(0);

const selectItem = (index: number) => {
    const item = props.items[index];
    if (item) {
        props.command(item);
    }
};

const upHandler = () => {
    selectedIndex.value = (selectedIndex.value + props.items.length - 1) % props.items.length;
};

const downHandler = () => {
    selectedIndex.value = (selectedIndex.value + 1) % props.items.length;
};

const enterHandler = () => {
    selectItem(selectedIndex.value);
};

const onKeyDown = (event: KeyboardEvent) => {
    if (event.key === 'ArrowUp') {
        upHandler();
        event.preventDefault();
        return true;
    }

    if (event.key === 'ArrowDown') {
        downHandler();
        event.preventDefault();
        return true;
    }

    if (event.key === 'Enter') {
        enterHandler();
        event.preventDefault();
        return true;
    }

    return false;
};

watch(
    () => props.items,
    () => {
        selectedIndex.value = 0;
    }
);

onMounted(() => {
    document.addEventListener('keydown', onKeyDown);
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeyDown);
});
</script>

<template>
    <div
        class="border-input bg-popover text-popover-foreground z-50 max-h-[300px] w-64 overflow-y-auto rounded-md border shadow-md"
    >
        <div v-if="items.length === 0" class="text-muted-foreground p-3 text-sm">Aucun emoji trouvé</div>
        <button
            v-for="(item, index) in items"
            :key="`${item.type}-${item.name}`"
            type="button"
            class="flex w-full items-center gap-3 px-3 py-2 text-left transition-colors hover:bg-accent"
            :class="{
                'bg-accent': index === selectedIndex,
            }"
            @click="selectItem(index)"
        >
            <!-- Native emoji -->
            <span v-if="item.type === 'native'" class="text-2xl">{{ item.emoji }}</span>

            <!-- Custom emoji -->
            <img
                v-else
                :src="item.preview_url"
                :alt="item.name"
                class="h-6 w-6 object-contain"
                loading="lazy"
            />

            <span class="font-mono text-sm">:{{ item.name }}:</span>
        </button>
    </div>
</template>
