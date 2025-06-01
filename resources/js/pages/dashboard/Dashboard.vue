<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';

interface VisitStats {
    totalVisitsPastTwentyFourHours: number;
    totalVisitsPastSevenDays: number;
    totalVisitsPastThirtyDays: number;
    totalVisitsAllTime: number;
    visitsPerDay: { date: string; count: number }[];
    visitsByCountry: { country_code: string; count: number }[];
    mostVisitedPages: { url: string; count: number }[];
    bestsReferrers: { url: string; count: number }[];
    bestOrigins: { url: string; count: number }[];
    periods: Record<string, string>;
    selectedPeriod: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const stats = ref<VisitStats | null>(null);
const loading = ref(true);
const selectedPeriod = ref('');
const customStartDate = ref('');
const customEndDate = ref('');

const loadStats = async (startDate?: string, endDate?: string) => {
    loading.value = true;
    try {
        const params: Record<string, string> = {};
        if (startDate) params.start_date = startDate;
        if (endDate) params.end_date = endDate;

        const response = await axios.get('/dashboard/stats', { params });
        const data = response.data;
        stats.value = data;
        if (!selectedPeriod.value) {
            selectedPeriod.value = data.selectedPeriod;
        }
    } catch (error) {
        console.error('Erreur lors du chargement des statistiques:', error);
    } finally {
        loading.value = false;
    }
};

const handlePeriodChange = (period: string) => {
    selectedPeriod.value = period;
    if (period === 'custom') return;

    const today = new Date().toISOString().split('T')[0];
    loadStats(period, today);
};

const applyCustomPeriod = () => {
    if (customStartDate.value && customEndDate.value) {
        loadStats(customStartDate.value, customEndDate.value);
    }
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('fr-FR');
};

const formatUrl = (url: string) => {
    if (!url) return 'Direct';
    try {
        const urlObj = new URL(url);
        return urlObj.pathname || urlObj.hostname;
    } catch {
        return url;
    }
};

const totalVisitsForPeriod = computed(() => {
    if (!stats.value) return 0;
    return stats.value.visitsPerDay.reduce((sum, day) => sum + day.count, 0);
});

onMounted(() => {
    loadStats();
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Sélecteur de période -->
            <Card>
                <CardHeader>
                    <CardTitle>Période d'analyse</CardTitle>
                    <CardDescription>Sélectionnez la période pour visualiser les statistiques</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Période prédéfinie</label>
                            <Select v-model="selectedPeriod" @update:model-value="handlePeriodChange">
                                <SelectTrigger class="w-48">
                                    <SelectValue placeholder="Choisir une période" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="(label, value) in stats?.periods" :key="value" :value="value">
                                        {{ label }}
                                    </SelectItem>
                                    <SelectItem value="custom">Période personnalisée</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div v-if="selectedPeriod === 'custom'" class="flex gap-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Date de début</label>
                                <input
                                    v-model="customStartDate"
                                    type="date"
                                    class="border-input bg-background flex h-10 w-full rounded-md border px-3 py-2 text-sm"
                                />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Date de fin</label>
                                <input
                                    v-model="customEndDate"
                                    type="date"
                                    class="border-input bg-background flex h-10 w-full rounded-md border px-3 py-2 text-sm"
                                />
                            </div>
                            <Button @click="applyCustomPeriod" class="mt-7"> Appliquer </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Statistiques générales -->
            <div class="grid auto-rows-min gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Visites 24h</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            <Skeleton v-if="loading" class="h-8 w-16" />
                            <span v-else>{{ stats?.totalVisitsPastTwentyFourHours || 0 }}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Visites 7 jours</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            <Skeleton v-if="loading" class="h-8 w-16" />
                            <span v-else>{{ stats?.totalVisitsPastSevenDays || 0 }}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Visites 30 jours</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            <Skeleton v-if="loading" class="h-8 w-16" />
                            <span v-else>{{ stats?.totalVisitsPastThirtyDays || 0 }}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total visites</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            <Skeleton v-if="loading" class="h-8 w-16" />
                            <span v-else>{{ stats?.totalVisitsAllTime || 0 }}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Graphiques et tableaux -->
            <div class="grid gap-4 md:grid-cols-2">
                <!-- Visites par jour -->
                <Card>
                    <CardHeader>
                        <CardTitle>Visites par jour</CardTitle>
                        <CardDescription>Évolution des visites sur la période sélectionnée ({{ totalVisitsForPeriod }} visites)</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="loading" class="space-y-2">
                            <Skeleton class="h-4 w-full" />
                            <Skeleton class="h-4 w-3/4" />
                            <Skeleton class="h-4 w-1/2" />
                        </div>
                        <div v-else-if="stats?.visitsPerDay?.length" class="space-y-2">
                            <div v-for="day in stats.visitsPerDay.slice(0, 10)" :key="day.date" class="flex items-center justify-between">
                                <span class="text-sm">{{ formatDate(day.date) }}</span>
                                <div class="flex items-center gap-2">
                                    <div class="bg-muted h-2 w-20 rounded-full">
                                        <div
                                            class="bg-primary h-2 rounded-full"
                                            :style="{ width: `${(day.count / Math.max(...stats.visitsPerDay.map((d) => d.count))) * 100}%` }"
                                        ></div>
                                    </div>
                                    <Badge variant="secondary">{{ day.count }}</Badge>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-muted-foreground text-sm">Aucune donnée disponible</p>
                    </CardContent>
                </Card>

                <!-- Visites par pays -->
                <Card>
                    <CardHeader>
                        <CardTitle>Visites par pays</CardTitle>
                        <CardDescription>Top 10 des pays visiteurs</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="loading" class="space-y-2">
                            <Skeleton class="h-4 w-full" />
                            <Skeleton class="h-4 w-3/4" />
                            <Skeleton class="h-4 w-1/2" />
                        </div>
                        <div v-else-if="stats?.visitsByCountry?.length" class="space-y-2">
                            <div
                                v-for="country in stats.visitsByCountry.slice(0, 10)"
                                :key="country.country_code"
                                class="flex items-center justify-between"
                            >
                                <span class="text-sm">{{ country.country_code || 'Inconnu' }}</span>
                                <Badge variant="outline">{{ country.count }}</Badge>
                            </div>
                        </div>
                        <p v-else class="text-muted-foreground text-sm">Aucune donnée disponible</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Tables détaillées -->
            <div class="grid gap-4 lg:grid-cols-3">
                <!-- Pages les plus visitées -->
                <Card>
                    <CardHeader>
                        <CardTitle>Pages populaires</CardTitle>
                        <CardDescription>Top 10 des pages les plus visitées</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="loading">
                            <Skeleton class="h-64 w-full" />
                        </div>
                        <Table v-else-if="stats?.mostVisitedPages?.length">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Page</TableHead>
                                    <TableHead class="text-right">Visites</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="page in stats.mostVisitedPages.slice(0, 10)" :key="page.url">
                                    <TableCell class="font-mono text-xs">{{ formatUrl(page.url) }}</TableCell>
                                    <TableCell class="text-right">{{ page.count }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                        <p v-else class="text-muted-foreground text-sm">Aucune donnée disponible</p>
                    </CardContent>
                </Card>

                <!-- Meilleurs référents -->
                <Card>
                    <CardHeader>
                        <CardTitle>Référents</CardTitle>
                        <CardDescription>Top 10 des sites référents</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="loading">
                            <Skeleton class="h-64 w-full" />
                        </div>
                        <Table v-else-if="stats?.bestsReferrers?.length">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Référent</TableHead>
                                    <TableHead class="text-right">Visites</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="referrer in stats.bestsReferrers.slice(0, 10)" :key="referrer.url">
                                    <TableCell class="font-mono text-xs">{{ formatUrl(referrer.url) }}</TableCell>
                                    <TableCell class="text-right">{{ referrer.count }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                        <p v-else class="text-muted-foreground text-sm">Aucune donnée disponible</p>
                    </CardContent>
                </Card>

                <!-- Meilleures origines -->
                <Card>
                    <CardHeader>
                        <CardTitle>Origines</CardTitle>
                        <CardDescription>Top 10 des origines de trafic</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="loading">
                            <Skeleton class="h-64 w-full" />
                        </div>
                        <Table v-else-if="stats?.bestOrigins?.length">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Origine</TableHead>
                                    <TableHead class="text-right">Visites</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="origin in stats.bestOrigins.slice(0, 10)" :key="origin.url">
                                    <TableCell class="font-mono text-xs">{{ formatUrl(origin.url) }}</TableCell>
                                    <TableCell class="text-right">{{ origin.count }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                        <p v-else class="text-muted-foreground text-sm">Aucune donnée disponible</p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
