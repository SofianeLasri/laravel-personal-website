<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
            <div class="space-y-6">
                <!-- Header -->
                <div>
                    <h1 class="text-2xl font-bold">Logs des requêtes API</h1>
                    <p class="text-muted-foreground">Historique et analyse des requêtes aux providers IA</p>
                </div>

                <!-- Statistics Panel -->
                <div data-testid="statistics-panel" class="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium">Total Requests</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ statistics.total }}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium">Success Rate</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ statistics.successRate }}%</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium">Cache Hit Rate</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ statistics.cacheHitRate }}%</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium">Total Cost</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">${{ statistics.totalCost }}</div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Filters -->
                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="flex gap-4">
                            <select
                                v-model="filters.provider"
                                data-testid="provider-filter"
                                class="border-input bg-background rounded-md border px-3 py-2 text-sm"
                            >
                                <option value="">All Providers</option>
                                <option value="openai">OpenAI</option>
                                <option value="anthropic">Anthropic</option>
                            </select>

                            <select
                                v-model="filters.status"
                                data-testid="status-filter"
                                class="border-input bg-background rounded-md border px-3 py-2 text-sm"
                            >
                                <option value="">All Status</option>
                                <option value="success">Success</option>
                                <option value="error">Error</option>
                            </select>

                            <select
                                v-model="filters.cached"
                                data-testid="cache-filter"
                                class="border-input bg-background rounded-md border px-3 py-2 text-sm"
                            >
                                <option value="">All Requests</option>
                                <option value="true">Cached</option>
                                <option value="false">Not Cached</option>
                            </select>
                        </div>
                    </CardContent>
                </Card>

                <!-- Logs Table -->
                <Card>
                    <CardHeader>
                        <CardTitle>Request Logs</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="relative overflow-x-auto">
                            <table data-testid="api-logs-table" class="w-full text-left text-sm">
                                <thead class="bg-muted text-xs uppercase">
                                    <tr>
                                        <th class="px-6 py-3">Provider</th>
                                        <th class="px-6 py-3">Model</th>
                                        <th class="px-6 py-3">Status</th>
                                        <th class="px-6 py-3">Tokens</th>
                                        <th class="px-6 py-3">Cost</th>
                                        <th class="px-6 py-3">Response Time</th>
                                        <th class="px-6 py-3">Cached</th>
                                        <th class="px-6 py-3">Date</th>
                                        <th class="px-6 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="log in paginatedLogs" :key="log.id" class="border-b">
                                        <td class="px-6 py-4">{{ log.provider }}</td>
                                        <td class="px-6 py-4">{{ log.model }}</td>
                                        <td class="px-6 py-4">
                                            <span :class="log.status === 'success' ? 'text-green-600' : 'text-red-600'">
                                                {{ log.status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">{{ log.input_tokens + log.output_tokens }}</td>
                                        <td class="px-6 py-4">${{ log.cost.toFixed(4) }}</td>
                                        <td class="px-6 py-4">{{ log.response_time }}ms</td>
                                        <td class="px-6 py-4">{{ log.cached ? 'Yes' : 'No' }}</td>
                                        <td class="px-6 py-4">{{ formatDate(log.created_at) }}</td>
                                        <td class="px-6 py-4">
                                            <Button size="sm" variant="ghost" :data-testid="`view-details-${log.id}`" @click="viewDetails(log)">
                                                View
                                            </Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div data-testid="pagination" class="mt-4 flex justify-center gap-2">
                            <Button
                                v-for="page in totalPages"
                                :key="page"
                                :data-testid="`page-${page}`"
                                :variant="currentPage === page ? 'default' : 'outline'"
                                size="sm"
                                @click="currentPage = page"
                            >
                                {{ page }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Details Modal -->
        <Dialog v-model:open="detailsOpen">
            <DialogContent class="max-w-4xl">
                <DialogHeader>
                    <DialogTitle>Détails de la requête</DialogTitle>
                </DialogHeader>
                <div v-if="selectedLog" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <h3 class="mb-2 font-semibold">System Prompt</h3>
                            <pre class="bg-muted rounded p-2 text-xs">{{ selectedLog.system_prompt }}</pre>
                        </div>
                        <div>
                            <h3 class="mb-2 font-semibold">User Prompt</h3>
                            <pre class="bg-muted rounded p-2 text-xs">{{ selectedLog.prompt }}</pre>
                        </div>
                    </div>
                    <div>
                        <h3 class="mb-2 font-semibold">Response</h3>
                        <pre class="bg-muted rounded p-2 text-xs">{{ selectedLog.response }}</pre>
                    </div>
                    <div class="grid grid-cols-3 gap-4 text-sm">
                        <div><span class="font-semibold">Input Tokens:</span> {{ selectedLog.input_tokens }}</div>
                        <div><span class="font-semibold">Output Tokens:</span> {{ selectedLog.output_tokens }}</div>
                        <div><span class="font-semibold">Cost:</span> ${{ selectedLog.cost.toFixed(4) }}</div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, onMounted, ref } from 'vue';

const breadcrumbs = [{ title: 'Dashboard', href: '/dashboard' }, { title: 'API Logs' }];

interface ApiLog {
    id: number;
    provider: string;
    model: string;
    system_prompt: string;
    prompt: string;
    response: string;
    input_tokens: number;
    output_tokens: number;
    cost: number;
    response_time: number;
    status: 'success' | 'error';
    cached: boolean;
    created_at: string;
}

const logs = ref<ApiLog[]>([]);
const filters = ref({
    provider: '',
    status: '',
    cached: '',
});
const currentPage = ref(1);
const perPage = 10;
const detailsOpen = ref(false);
const selectedLog = ref<ApiLog | null>(null);

const statistics = ref({
    total: 0,
    successRate: 0,
    cacheHitRate: 0,
    totalCost: 0,
});

const filteredLogs = computed(() => {
    return logs.value.filter((log) => {
        if (filters.value.provider && log.provider !== filters.value.provider) return false;
        if (filters.value.status && log.status !== filters.value.status) return false;
        if (filters.value.cached !== '' && log.cached !== (filters.value.cached === 'true')) return false;
        return true;
    });
});

const totalPages = computed(() => Math.ceil(filteredLogs.value.length / perPage));

const paginatedLogs = computed(() => {
    const start = (currentPage.value - 1) * perPage;
    return filteredLogs.value.slice(start, start + perPage);
});

const formatDate = (date: string) => {
    return new Date(date).toLocaleString();
};

const viewDetails = (log: ApiLog) => {
    selectedLog.value = log;
    detailsOpen.value = true;
};

// Function commented out as it's not currently used - dummy data is used instead
// const fetchLogs = async () => {
//     try {
//         const response = await axios.get('/dashboard/api/api-logs');
//         logs.value = response.data.data || [];
//         calculateStatistics();
//     } catch (error) {
//         console.error('Failed to fetch API logs:', error);
//     }
// };

const calculateStatistics = () => {
    const total = logs.value.length;
    const successful = logs.value.filter((log) => log.status === 'success').length;
    const cached = logs.value.filter((log) => log.cached).length;
    const totalCost = logs.value.reduce((sum, log) => sum + log.cost, 0);

    statistics.value = {
        total,
        successRate: total > 0 ? Math.round((successful / total) * 100) : 0,
        cacheHitRate: total > 0 ? Math.round((cached / total) * 100) : 0,
        totalCost: Math.round(totalCost * 10000) / 10000,
    };
};

onMounted(() => {
    // Create some dummy data for testing
    logs.value = [
        {
            id: 1,
            provider: 'openai',
            model: 'gpt-4',
            system_prompt: 'You are a helpful assistant for testing',
            prompt: 'This is a test prompt for viewing details',
            response: 'This is a test response',
            input_tokens: 100,
            output_tokens: 200,
            cost: 0.012,
            response_time: 1500,
            status: 'success',
            cached: false,
            created_at: new Date().toISOString(),
        },
        {
            id: 2,
            provider: 'anthropic',
            model: 'claude-3',
            system_prompt: 'System prompt',
            prompt: 'User prompt',
            response: 'Response',
            input_tokens: 50,
            output_tokens: 150,
            cost: 0.008,
            response_time: 1200,
            status: 'success',
            cached: true,
            created_at: new Date().toISOString(),
        },
        {
            id: 3,
            provider: 'openai',
            model: 'gpt-3.5-turbo',
            system_prompt: 'System',
            prompt: 'Prompt',
            response: 'Error occurred',
            input_tokens: 30,
            output_tokens: 0,
            cost: 0,
            response_time: 500,
            status: 'error',
            cached: false,
            created_at: new Date().toISOString(),
        },
        {
            id: 4,
            provider: 'openai',
            model: 'gpt-4',
            system_prompt: 'Assistant',
            prompt: 'Test',
            response: 'Response',
            input_tokens: 80,
            output_tokens: 120,
            cost: 0.01,
            response_time: 1100,
            status: 'success',
            cached: false,
            created_at: new Date().toISOString(),
        },
        {
            id: 5,
            provider: 'anthropic',
            model: 'claude-3',
            system_prompt: 'Helper',
            prompt: 'Question',
            response: 'Answer',
            input_tokens: 60,
            output_tokens: 180,
            cost: 0.009,
            response_time: 1300,
            status: 'success',
            cached: true,
            created_at: new Date().toISOString(),
        },
        {
            id: 6,
            provider: 'openai',
            model: 'gpt-4',
            system_prompt: 'System',
            prompt: 'Query',
            response: 'Result',
            input_tokens: 90,
            output_tokens: 210,
            cost: 0.013,
            response_time: 1600,
            status: 'success',
            cached: false,
            created_at: new Date().toISOString(),
        },
    ];

    calculateStatistics();
});
</script>
