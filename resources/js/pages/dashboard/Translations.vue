<template>
    <Head title="Translations" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Stats Cards -->
            <div class="grid auto-rows-min gap-4 md:grid-cols-4">
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-xl border p-6">
                    <h3 class="text-muted-foreground text-sm font-medium">Total des clés</h3>
                    <p class="text-2xl font-bold">{{ stats.total_keys }}</p>
                </div>
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-xl border p-6">
                    <h3 class="text-muted-foreground text-sm font-medium">Traductions françaises</h3>
                    <p class="text-2xl font-bold text-blue-600">{{ stats.french_translations }}</p>
                </div>
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-xl border p-6">
                    <h3 class="text-muted-foreground text-sm font-medium">Traductions anglaises</h3>
                    <p class="text-2xl font-bold text-green-600">{{ stats.english_translations }}</p>
                </div>
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-xl border p-6">
                    <h3 class="text-muted-foreground text-sm font-medium">Anglaises manquantes</h3>
                    <p class="text-destructive text-2xl font-bold">{{ stats.missing_english }}</p>
                </div>
            </div>

            <!-- Main Content -->
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative flex-1 rounded-xl border">
                <div class="border-sidebar-border/70 border-b px-6 py-4">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex flex-1 flex-col gap-4 sm:flex-row">
                            <!-- Search -->
                            <div class="max-w-md flex-1">
                                <Input v-model="searchQuery" placeholder="Rechercher des traductions..." @input="debouncedSearch" />
                            </div>

                            <!-- Locale Filter -->
                            <Select v-model="localeFilter">
                                <SelectTrigger class="w-40">
                                    <SelectValue placeholder="Toutes les langues" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Toutes les langues</SelectItem>
                                    <SelectItem value="fr">Français uniquement</SelectItem>
                                    <SelectItem value="en">Anglais uniquement</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Batch Actions -->
                        <div class="flex gap-2">
                            <Button variant="outline" @click="translateBatch('missing')" :disabled="isTranslating">
                                <LanguagesIcon class="mr-2 h-4 w-4" />
                                Traduire manquantes
                            </Button>
                            <Button variant="outline" @click="translateBatch('all')" :disabled="isTranslating">
                                <RefreshCwIcon class="mr-2 h-4 w-4" />
                                Retraduire toutes
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-64">Clé</TableHead>
                                <TableHead>Texte français</TableHead>
                                <TableHead>Texte anglais</TableHead>
                                <TableHead class="w-32">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="translationKey in translationKeys.data" :key="translationKey.id">
                                <TableCell class="font-mono text-sm">
                                    {{ translationKey.key }}
                                </TableCell>
                                <TableCell>
                                    <div class="max-w-md">
                                        <TranslationCell :translation="getFrenchTranslation(translationKey)" locale="fr" @save="updateTranslation" />
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div class="max-w-md">
                                        <TranslationCell
                                            :translation="getEnglishTranslation(translationKey)"
                                            locale="en"
                                            @save="updateTranslation"
                                            :can-translate="!getEnglishTranslation(translationKey) && !!getFrenchTranslation(translationKey)"
                                            @translate="translateSingle(translationKey)"
                                        />
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Button
                                        v-if="!getEnglishTranslation(translationKey) && !!getFrenchTranslation(translationKey)"
                                        variant="ghost"
                                        size="sm"
                                        @click="translateSingle(translationKey)"
                                        :disabled="isTranslating"
                                    >
                                        <LanguagesIcon class="h-4 w-4" />
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- Pagination -->
                <div v-if="translationKeys.data.length > 0" class="border-sidebar-border/70 border-t px-6 py-4">
                    <Pagination
                        :current-page="translationKeys.current_page"
                        :last-page="translationKeys.last_page"
                        :per-page="translationKeys.per_page"
                        :total="translationKeys.total"
                        @navigate="navigateToPage"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import Pagination from '@/components/dashboard/Pagination.vue';
import TranslationCell from '@/components/dashboard/TranslationCell.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import debounce from 'lodash.debounce';
import { LanguagesIcon, RefreshCwIcon } from 'lucide-vue-next';
import { ref, watch } from 'vue';

// import { toast } from '@/components/ui/toast'

interface Translation {
    id: number;
    locale: string;
    text: string;
}

interface TranslationKey {
    id: number;
    key: string;
    translations: Translation[];
}

interface PaginatedData {
    data: TranslationKey[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Stats {
    total_keys: number;
    french_translations: number;
    english_translations: number;
    missing_english: number;
}

const props = defineProps<{
    translationKeys: PaginatedData;
    filters: {
        search: string;
        locale: string;
        per_page: number;
    };
    stats: Stats;
}>();

const breadcrumbs: BreadcrumbItemType[] = [
    {
        title: 'Dashboard',
        href: route('dashboard.index'),
    },
    {
        title: 'Translations',
        href: route('dashboard.translations.index'),
    },
];

const searchQuery = ref(props.filters.search || '');
const localeFilter = ref(props.filters.locale || 'all');
const isTranslating = ref(false);

const debouncedSearch = debounce(() => {
    updateFilters();
}, 300);

watch(localeFilter, () => {
    updateFilters();
});

function updateFilters() {
    router.get(
        route('dashboard.translations.index'),
        {
            search: searchQuery.value,
            locale: localeFilter.value,
            per_page: props.filters.per_page,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
}

function navigateToPage(page: number) {
    router.get(
        route('dashboard.translations.index'),
        {
            search: searchQuery.value,
            locale: localeFilter.value,
            per_page: props.filters.per_page,
            page,
        },
        {
            preserveState: true,
        },
    );
}

function getFrenchTranslation(translationKey: TranslationKey): Translation | null {
    return translationKey.translations.find((t) => t.locale === 'fr') || null;
}

function getEnglishTranslation(translationKey: TranslationKey): Translation | null {
    return translationKey.translations.find((t) => t.locale === 'en') || null;
}

async function updateTranslation(translation: Translation, newText: string) {
    try {
        const response = await axios.put(route('dashboard.api.translations.update', translation.id), {
            text: newText,
        });

        if (response.data.success) {
            // Successfully updated - update the translation in the current data
            translation.text = newText;
        } else {
            alert('Échec de la mise à jour de la traduction');
        }
    } catch (error) {
        console.error('Failed to update translation:', error);
        if (axios.isAxiosError(error) && error.response?.data?.message) {
            alert(error.response.data.message);
        } else {
            alert('Échec de la mise à jour de la traduction');
        }
    }
}

async function translateSingle(translationKey: TranslationKey) {
    isTranslating.value = true;

    try {
        const response = await axios.post(route('dashboard.api.translations.translate-single', translationKey.id), {});

        if (response.data.success) {
            alert('Tâche de traduction mise en file d\'attente avec succès');
        } else {
            alert(response.data.message || 'Échec de la mise en file d\'attente de la traduction');
        }
    } catch (error) {
        console.error('Failed to translate:', error);
        if (axios.isAxiosError(error) && error.response?.data?.message) {
            alert(error.response.data.message);
        } else {
            alert('Échec de la mise en file d\'attente de la traduction');
        }
    } finally {
        isTranslating.value = false;
    }
}

async function translateBatch(mode: 'missing' | 'all') {
    isTranslating.value = true;

    try {
        const response = await axios.post(route('dashboard.api.translations.translate-batch'), {
            mode,
        });

        if (response.data.success) {
            alert(`${response.data.jobs_dispatched || 0} tâches de traduction mises en file d'attente`);
        } else {
            alert(response.data.message || 'Échec de la mise en file d\'attente des traductions');
        }
    } catch (error) {
        console.error('Failed to batch translate:', error);
        if (axios.isAxiosError(error) && error.response?.data?.message) {
            alert(error.response.data.message);
        } else {
            alert('Échec de la mise en file d\'attente des traductions');
        }
    } finally {
        isTranslating.value = false;
    }
}
</script>
