<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { getEmojiCategories, nativeEmojis, searchEmojis, type NativeEmoji } from '@/data/native-emojis';
import axios from 'axios';
import { Loader2, Smile } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface CustomEmoji {
    name: string;
    preview_url: string;
}

interface Props {
    open?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    open: false,
});

const emit = defineEmits<{
    'update:open': [value: boolean];
    'select': [emoji: string];
}>();

// State
const isOpen = ref(props.open);
const searchQuery = ref('');
const activeTab = ref<'native' | 'custom'>('native');
const customEmojis = ref<CustomEmoji[]>([]);
const loadingCustomEmojis = ref(false);
const selectedCategory = ref<string>('All');

// Computed
const categories = computed(() => ['All', ...getEmojiCategories()]);

const filteredNativeEmojis = computed(() => {
    if (searchQuery.value) {
        return searchEmojis(searchQuery.value);
    }

    if (selectedCategory.value === 'All') {
        return nativeEmojis;
    }

    return nativeEmojis.filter((emoji) => emoji.category === selectedCategory.value);
});

const filteredCustomEmojis = computed(() => {
    if (!searchQuery.value) {
        return customEmojis.value;
    }

    const query = searchQuery.value.toLowerCase();
    return customEmojis.value.filter((emoji) => emoji.name.toLowerCase().includes(query));
});

// Methods
const loadCustomEmojis = async () => {
    loadingCustomEmojis.value = true;
    try {
        const response = await axios.get('/dashboard/api/custom-emojis-for-editor');
        customEmojis.value = response.data;
    } catch (error) {
        console.error('Failed to load custom emojis:', error);
    } finally {
        loadingCustomEmojis.value = false;
    }
};

const selectNativeEmoji = (emoji: NativeEmoji) => {
    emit('select', emoji.emoji);
    isOpen.value = false;
    searchQuery.value = '';
};

const selectCustomEmoji = (emoji: CustomEmoji) => {
    emit('select', `:${emoji.name}:`);
    isOpen.value = false;
    searchQuery.value = '';
};

const handleOpenChange = (open: boolean) => {
    isOpen.value = open;
    emit('update:open', open);

    if (open && customEmojis.value.length === 0) {
        void loadCustomEmojis();
    }
};

// Watchers
watch(
    () => props.open,
    (newValue) => {
        isOpen.value = newValue;
    }
);

watch(isOpen, (newValue) => {
    emit('update:open', newValue);
});

// Lifecycle
onMounted(() => {
    if (isOpen.value && customEmojis.value.length === 0) {
        void loadCustomEmojis();
    }
});
</script>

<template>
    <Popover :open="isOpen" @update:open="handleOpenChange">
        <PopoverTrigger as-child>
            <slot>
                <Button variant="ghost" size="sm" type="button" title="Insérer un emoji">
                    <Smile class="h-4 w-4" />
                </Button>
            </slot>
        </PopoverTrigger>
        <PopoverContent class="w-[400px] p-0" align="start">
            <Tabs v-model="activeTab" class="w-full">
                <div class="border-b p-2">
                    <TabsList class="grid w-full grid-cols-2">
                        <TabsTrigger value="native">Natifs</TabsTrigger>
                        <TabsTrigger value="custom">Personnalisés</TabsTrigger>
                    </TabsList>
                </div>

                <div class="p-3">
                    <Input
                        v-model="searchQuery"
                        placeholder="Rechercher un emoji..."
                        class="mb-3"
                        @keydown.enter.prevent
                    />
                </div>

                <!-- Native Emojis Tab -->
                <TabsContent value="native" class="m-0 max-h-[300px] overflow-y-auto p-3">
                    <div v-if="!searchQuery" class="mb-3">
                        <div class="flex flex-wrap gap-1">
                            <Button
                                v-for="category in categories"
                                :key="category"
                                :variant="selectedCategory === category ? 'default' : 'outline'"
                                size="sm"
                                class="h-7 text-xs"
                                @click="selectedCategory = category"
                            >
                                {{ category }}
                            </Button>
                        </div>
                    </div>

                    <div v-if="filteredNativeEmojis.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        Aucun emoji trouvé
                    </div>

                    <div v-else class="grid grid-cols-8 gap-1">
                        <button
                            v-for="emoji in filteredNativeEmojis"
                            :key="emoji.name"
                            type="button"
                            class="hover:bg-muted flex h-10 w-10 items-center justify-center rounded text-2xl transition-colors"
                            :title="`:${emoji.name}:`"
                            @click="selectNativeEmoji(emoji)"
                        >
                            {{ emoji.emoji }}
                        </button>
                    </div>
                </TabsContent>

                <!-- Custom Emojis Tab -->
                <TabsContent value="custom" class="m-0 max-h-[300px] overflow-y-auto p-3">
                    <div v-if="loadingCustomEmojis" class="flex items-center justify-center py-8">
                        <Loader2 class="text-muted-foreground h-6 w-6 animate-spin" />
                    </div>

                    <div v-else-if="customEmojis.length === 0" class="py-8 text-center">
                        <Smile class="text-muted-foreground mx-auto mb-2 h-8 w-8" />
                        <p class="text-muted-foreground text-sm">Aucun emoji personnalisé</p>
                        <p class="text-muted-foreground mt-1 text-xs">
                            Ajoutez-en dans
                            <a href="/dashboard/custom-emojis" class="text-primary underline">la gestion des emojis</a>
                        </p>
                    </div>

                    <div v-else-if="filteredCustomEmojis.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        Aucun emoji trouvé
                    </div>

                    <div v-else class="grid grid-cols-8 gap-1">
                        <button
                            v-for="emoji in filteredCustomEmojis"
                            :key="emoji.name"
                            type="button"
                            class="hover:bg-muted flex h-10 w-10 items-center justify-center overflow-hidden rounded transition-colors"
                            :title="`:${emoji.name}:`"
                            @click="selectCustomEmoji(emoji)"
                        >
                            <img
                                :src="emoji.preview_url"
                                :alt="emoji.name"
                                class="h-8 w-8 object-contain"
                                loading="lazy"
                            />
                        </button>
                    </div>
                </TabsContent>
            </Tabs>
        </PopoverContent>
    </Popover>
</template>
