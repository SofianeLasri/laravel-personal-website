<script setup lang="ts">
import Heading from '@/components/dashboard/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';
import { ArrowLeft, DollarSign, Hash, Package, Server, Zap } from 'lucide-vue-next';

interface ApiLog {
    id: number;
    provider: string;
    model: string;
    endpoint: string;
    status: string;
    status_color: string;
    http_status_code: number | null;
    error_message: string | null;
    system_prompt: string;
    user_prompt: string;
    response: any;
    prompt_tokens: number | null;
    completion_tokens: number | null;
    total_tokens: number | null;
    response_time: number;
    estimated_cost: number | null;
    fallback_provider: string | null;
    metadata: any;
    cached: boolean;
    created_at: string;
}

interface Props {
    log: ApiLog;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Logs API',
        href: route('dashboard.api-logs.index', undefined, false),
    },
    {
        title: `Log #${props.log.id}`,
        href: route('dashboard.api-logs.show', props.log.id, false),
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

const goBack = () => {
    router.get(route('dashboard.api-logs.index'));
};
</script>

<template>
    <AppLayout>
        <Head :title="`Log API #${log.id}`" />
        <Heading :title="`Détails du log #${log.id}`" :breadcrumbs="breadcrumbs">
            <template #actions>
                <Button @click="goBack" variant="outline">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Retour à la liste
                </Button>
            </template>
        </Heading>

        <!-- Overview Cards -->
        <div class="mb-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Provider</CardTitle>
                    <Server class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ log.provider }}</div>
                    <p class="text-muted-foreground text-xs">{{ log.model }}</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Tokens</CardTitle>
                    <Hash class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ log.total_tokens || '-' }}</div>
                    <p class="text-muted-foreground text-xs">
                        <span v-if="log.prompt_tokens">Prompt: {{ log.prompt_tokens }}</span>
                        <span v-if="log.prompt_tokens && log.completion_tokens"> | </span>
                        <span v-if="log.completion_tokens">Completion: {{ log.completion_tokens }}</span>
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Temps de réponse</CardTitle>
                    <Zap class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ formatResponseTime(log.response_time) }}</div>
                    <p class="text-muted-foreground text-xs">
                        <Badge v-if="log.cached" variant="secondary">
                            <Package class="mr-1 h-3 w-3" />
                            Depuis le cache
                        </Badge>
                        <span v-else>Appel API direct</span>
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Coût estimé</CardTitle>
                    <DollarSign class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ formatCost(log.estimated_cost) }}</div>
                    <p class="text-muted-foreground text-xs">USD</p>
                </CardContent>
            </Card>
        </div>

        <!-- Main Details -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Request Information -->
            <Card>
                <CardHeader>
                    <CardTitle>Informations de la requête</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div>
                        <div class="text-muted-foreground text-sm font-medium">Statut</div>
                        <div class="mt-1">
                            <Badge :variant="getStatusBadgeVariant(log.status)">
                                {{ log.status }}
                            </Badge>
                            <span v-if="log.http_status_code" class="text-muted-foreground ml-2 text-sm"> HTTP {{ log.http_status_code }} </span>
                        </div>
                    </div>

                    <div>
                        <div class="text-muted-foreground text-sm font-medium">Endpoint</div>
                        <div class="mt-1 font-mono text-sm">{{ log.endpoint }}</div>
                    </div>

                    <div>
                        <div class="text-muted-foreground text-sm font-medium">Date</div>
                        <div class="mt-1">{{ formatDate(log.created_at) }}</div>
                    </div>

                    <div v-if="log.fallback_provider">
                        <div class="text-muted-foreground text-sm font-medium">Fallback Provider</div>
                        <div class="mt-1">
                            <Badge variant="outline">{{ log.fallback_provider }}</Badge>
                        </div>
                    </div>

                    <div v-if="log.error_message">
                        <div class="text-muted-foreground text-sm font-medium">Message d'erreur</div>
                        <div class="bg-destructive/10 text-destructive mt-1 rounded-md p-3 text-sm">
                            {{ log.error_message }}
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Metadata -->
            <Card v-if="log.metadata">
                <CardHeader>
                    <CardTitle>Métadonnées</CardTitle>
                </CardHeader>
                <CardContent>
                    <pre class="bg-muted overflow-auto rounded-md p-3 text-xs">{{ JSON.stringify(log.metadata, null, 2) }}</pre>
                </CardContent>
            </Card>
        </div>

        <!-- Prompts -->
        <Card class="mt-6">
            <CardHeader>
                <CardTitle>Prompts</CardTitle>
            </CardHeader>
            <CardContent class="space-y-6">
                <div>
                    <div class="text-muted-foreground mb-2 text-sm font-medium">System Prompt</div>
                    <div class="bg-muted rounded-md p-4 text-sm whitespace-pre-wrap">{{ log.system_prompt }}</div>
                </div>

                <Separator />

                <div>
                    <div class="text-muted-foreground mb-2 text-sm font-medium">User Prompt</div>
                    <div class="bg-muted rounded-md p-4 text-sm whitespace-pre-wrap">{{ log.user_prompt }}</div>
                </div>
            </CardContent>
        </Card>

        <!-- Response -->
        <Card v-if="log.response" class="mt-6">
            <CardHeader>
                <CardTitle>Réponse</CardTitle>
                <CardDescription>Réponse complète de l'API</CardDescription>
            </CardHeader>
            <CardContent>
                <pre class="bg-muted overflow-auto rounded-md p-4 text-xs">{{ JSON.stringify(log.response, null, 2) }}</pre>
            </CardContent>
        </Card>
    </AppLayout>
</template>
