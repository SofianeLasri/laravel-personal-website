<script setup lang="ts">
import Heading from '@/components/dashboard/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationFirst,
    PaginationItem,
    PaginationLast,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';
import { AlertCircle, CheckCircle, Clock, DollarSign, Eye, Package, RotateCcw, Search, Server, Zap } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface ApiLog {
    id: number;
    provider: string;
    model: string;
    status: string;
    status_color: string;
    http_status_code: number | null;
    system_prompt_truncated: string;
    user_prompt_truncated: string;
    system_prompt: string;
    user_prompt: string;
    error_message: string | null;
    prompt_tokens: number | null;
    completion_tokens: number | null;
    total_tokens: number | null;
    response_time: number;
    estimated_cost: number | null;
    cached: boolean;
    created_at: string;
    created_at_iso: string;
}

interface PaginatedLogs {
    data: ApiLog[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Statistics {
    total_requests: number;
    successful_requests: number;
    error_requests: number;
    timeout_requests: number;
    cached_requests: number;
    total_cost: number;
    average_response_time: number;
    total_tokens: number;
    by_provider: Record<
        string,
        {
            count: number;
            cost: number;
            tokens: number;
            avg_response_time: number;
        }
    >;
    by_status: Record<string, number>;
}

interface Props {
    logs: PaginatedLogs;
    filters: {
        search: string | null;
        per_page: number;
        provider: string;
        status: string;
        cached: string;
        date_from: string | null;
        date_to: string | null;
    };
    statistics: Statistics;
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search || '');
const perPage = ref(props.filters.per_page.toString());
const providerFilter = ref(props.filters.provider || 'all');
const statusFilter = ref(props.filters.status || 'all');
const cachedFilter = ref(props.filters.cached || 'all');
const dateFrom = ref(props.filters.date_from || '');
const dateTo = ref(props.filters.date_to || '');

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Logs API',
        href: route('dashboard.api-logs.index', undefined, false),
    },
];

const formatDate = (dateString: string) => {
    return format(new Date(dateString), 'dd/MM/yyyy HH:mm:ss', { locale: fr });
};

const formatCost = (cost: number | null) => {
    if (cost === null) return '-';
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 4,
        maximumFractionDigits: 6,
    }).format(cost);
};

const formatResponseTime = (time: number) => {
    if (time < 1) return `${(time * 1000).toFixed(0)}ms`;
    return `${time.toFixed(2)}s`;
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'success':
            return CheckCircle;
        case 'error':
            return AlertCircle;
        case 'timeout':
            return Clock;
        default:
            return Server;
    }
};

const getStatusBadgeVariant = (status: string) => {
    switch (status) {
        case 'success':
            return 'default';
        case 'error':
            return 'destructive';
        case 'timeout':
            return 'secondary';
        case 'fallback':
            return 'outline';
        default:
            return 'secondary';
    }
};

const debouncedSearch = computed(() => {
    return searchQuery.value;
});

watch(
    [debouncedSearch, perPage, providerFilter, statusFilter, cachedFilter, dateFrom, dateTo],
    () => {
        applyFilters();
    },
    { immediate: false },
);

const applyFilters = () => {
    router.get(
        route('dashboard.api-logs.index'),
        {
            search: searchQuery.value || undefined,
            per_page: parseInt(perPage.value),
            provider: providerFilter.value !== 'all' ? providerFilter.value : undefined,
            status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
            cached: cachedFilter.value !== 'all' ? cachedFilter.value : undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const resetFilters = () => {
    searchQuery.value = '';
    perPage.value = '15';
    providerFilter.value = 'all';
    statusFilter.value = 'all';
    cachedFilter.value = 'all';
    dateFrom.value = '';
    dateTo.value = '';
    applyFilters();
};

const viewLog = (logId: number) => {
    router.get(route('dashboard.api-logs.show', logId));
};

const goToPage = (page: number) => {
    router.get(
        route('dashboard.api-logs.index'),
        {
            page,
            search: searchQuery.value || undefined,
            per_page: parseInt(perPage.value),
            provider: providerFilter.value !== 'all' ? providerFilter.value : undefined,
            status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
            cached: cachedFilter.value !== 'all' ? cachedFilter.value : undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const generatePageNumbers = () => {
    const current = props.logs.current_page;
    const last = props.logs.last_page;
    const delta = 2; // Nombre de pages à afficher de chaque côté de la page courante
    const range = [];
    const rangeWithDots = [];
    let l;

    for (let i = 1; i <= last; i++) {
        if (i === 1 || i === last || (i >= current - delta && i <= current + delta)) {
            range.push(i);
        }
    }

    range.forEach((i) => {
        if (l) {
            if (i - l === 2) {
                rangeWithDots.push({ type: 'page', value: l + 1 });
            } else if (i - l !== 1) {
                rangeWithDots.push({ type: 'ellipsis' });
            }
        }
        rangeWithDots.push({ type: 'page', value: i });
        l = i;
    });

    return rangeWithDots;
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Logs API" />

        <div class="px-5 py-6">
            <div class="flex items-center justify-between">
                <Heading title="Logs des requêtes API" description="Visualisez toutes les requêtes API vers les services IA." />
            </div>

            <!-- Statistics Cards -->
            <div v-if="statistics" class="mb-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Requêtes</CardTitle>
                        <Server class="text-muted-foreground h-4 w-4" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ statistics.total_requests }}</div>
                        <p class="text-muted-foreground text-xs">{{ statistics.cached_requests }} cachées</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Taux de Succès</CardTitle>
                        <CheckCircle class="text-muted-foreground h-4 w-4" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ statistics.total_requests > 0 ? ((statistics.successful_requests / statistics.total_requests) * 100).toFixed(1) : 0 }}%
                        </div>
                        <p class="text-muted-foreground text-xs">{{ statistics.error_requests }} erreurs</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Coût Total</CardTitle>
                        <DollarSign class="text-muted-foreground h-4 w-4" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ formatCost(statistics.total_cost) }}</div>
                        <p class="text-muted-foreground text-xs">{{ statistics.total_tokens }} tokens</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Temps Moyen</CardTitle>
                        <Zap class="text-muted-foreground h-4 w-4" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ formatResponseTime(statistics.average_response_time || 0) }}</div>
                        <p class="text-muted-foreground text-xs">Temps de réponse</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters -->
            <Card class="mb-6">
                <CardHeader>
                    <CardTitle>Filtres</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <div class="space-y-2">
                            <Label for="search">Recherche</Label>
                            <div class="relative">
                                <Search class="text-muted-foreground absolute top-2.5 left-2 h-4 w-4" />
                                <Input id="search" v-model="searchQuery" placeholder="Rechercher..." class="pl-8" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="provider">Provider</Label>
                            <Select v-model="providerFilter">
                                <SelectTrigger id="provider">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Tous</SelectItem>
                                    <SelectItem value="openai">OpenAI</SelectItem>
                                    <SelectItem value="anthropic">Anthropic</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <Label for="status">Statut</Label>
                            <Select v-model="statusFilter">
                                <SelectTrigger id="status">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Tous</SelectItem>
                                    <SelectItem value="success">Succès</SelectItem>
                                    <SelectItem value="error">Erreur</SelectItem>
                                    <SelectItem value="timeout">Timeout</SelectItem>
                                    <SelectItem value="fallback">Fallback</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <Label for="cached">Cache</Label>
                            <Select v-model="cachedFilter">
                                <SelectTrigger id="cached">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Tous</SelectItem>
                                    <SelectItem value="true">Caché</SelectItem>
                                    <SelectItem value="false">Non caché</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <Label for="date-from">Date de début</Label>
                            <Input id="date-from" v-model="dateFrom" type="date" />
                        </div>

                        <div class="space-y-2">
                            <Label for="date-to">Date de fin</Label>
                            <Input id="date-to" v-model="dateTo" type="date" />
                        </div>

                        <div class="space-y-2">
                            <Label for="per-page">Par page</Label>
                            <Select v-model="perPage">
                                <SelectTrigger id="per-page">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="10">10</SelectItem>
                                    <SelectItem value="15">15</SelectItem>
                                    <SelectItem value="25">25</SelectItem>
                                    <SelectItem value="50">50</SelectItem>
                                    <SelectItem value="100">100</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="flex items-end">
                            <Button @click="resetFilters" variant="outline" class="w-full">
                                <RotateCcw class="mr-2 h-4 w-4" />
                                Réinitialiser
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Logs Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Requêtes API</CardTitle>
                    <CardDescription>
                        Affichage de {{ props.logs.from ?? 0 }} à {{ props.logs.to ?? 0 }} sur {{ props.logs.total }} entrées
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="relative overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Provider</TableHead>
                                    <TableHead>Modèle</TableHead>
                                    <TableHead>Statut</TableHead>
                                    <TableHead>Prompts</TableHead>
                                    <TableHead class="text-right">Tokens</TableHead>
                                    <TableHead class="text-right">Temps</TableHead>
                                    <TableHead class="text-right">Coût</TableHead>
                                    <TableHead>Cache</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="log in logs.data" :key="log.id">
                                    <TableCell class="font-mono text-xs">
                                        {{ formatDate(log.created_at) }}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="outline">{{ log.provider }}</Badge>
                                    </TableCell>
                                    <TableCell class="text-xs">
                                        {{ log.model }}
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="getStatusBadgeVariant(log.status)">
                                            {{ log.status }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="max-w-xs">
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger>
                                                    <div class="text-muted-foreground truncate text-xs">
                                                        System: {{ log.system_prompt_truncated }}
                                                    </div>
                                                    <div class="truncate text-xs">User: {{ log.user_prompt_truncated }}</div>
                                                </TooltipTrigger>
                                                <TooltipContent class="max-w-md">
                                                    <div class="space-y-2">
                                                        <div>
                                                            <strong>System:</strong>
                                                            <p class="text-xs">{{ log.system_prompt }}</p>
                                                        </div>
                                                        <div>
                                                            <strong>User:</strong>
                                                            <p class="text-xs">{{ log.user_prompt }}</p>
                                                        </div>
                                                    </div>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </TableCell>
                                    <TableCell class="text-right font-mono text-xs">
                                        {{ log.total_tokens || '-' }}
                                    </TableCell>
                                    <TableCell class="text-right font-mono text-xs">
                                        {{ formatResponseTime(log.response_time) }}
                                    </TableCell>
                                    <TableCell class="text-right font-mono text-xs">
                                        {{ formatCost(log.estimated_cost) }}
                                    </TableCell>
                                    <TableCell>
                                        <Badge v-if="log.cached" variant="secondary">
                                            <Package class="mr-1 h-3 w-3" />
                                            Caché
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <Button @click="viewLog(log.id)" variant="ghost" size="sm">
                                            <Eye class="h-4 w-4" />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="logs.data.length === 0">
                                    <TableCell colspan="10" class="text-muted-foreground text-center"> Aucune requête trouvée </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="logs.last_page > 1" class="mt-4 flex flex-col items-center space-y-4">
                        <Pagination
                            :total="logs.total"
                            :items-per-page="logs.per_page"
                            :default-page="logs.current_page"
                            show-edges
                            :sibling-count="2"
                            v-slot="{ page }"
                        >
                            <PaginationContent class="flex items-center gap-1">
                                <PaginationFirst @click="goToPage(1)" :disabled="logs.current_page === 1" />
                                <PaginationPrevious @click="goToPage(logs.current_page - 1)" :disabled="logs.current_page === 1" />

                                <template v-for="(item, index) in generatePageNumbers()" :key="index">
                                    <PaginationItem v-if="item.type === 'page'" :value="item.value" as-child>
                                        <Button
                                            class="h-9 min-w-[36px] px-3"
                                            :variant="item.value === logs.current_page ? 'default' : 'outline'"
                                            @click="goToPage(item.value)"
                                        >
                                            {{ item.value }}
                                        </Button>
                                    </PaginationItem>
                                    <PaginationEllipsis v-else />
                                </template>

                                <PaginationNext @click="goToPage(logs.current_page + 1)" :disabled="logs.current_page === logs.last_page" />
                                <PaginationLast @click="goToPage(logs.last_page)" :disabled="logs.current_page === logs.last_page" />
                            </PaginationContent>
                        </Pagination>

                        <div class="text-muted-foreground text-sm">
                            Page {{ logs.current_page }} sur {{ logs.last_page }} • Affichage de {{ logs.from ?? 0 }} à {{ logs.to ?? 0 }} sur
                            {{ logs.total }} entrées
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
