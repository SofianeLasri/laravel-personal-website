<script setup lang="ts">
import Heading from '@/components/dashboard/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';
import { Bot, ChevronLeft, ChevronRight, Flag, Globe, RotateCcw, Search, User } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface RequestLog {
    id: number;
    ip_address: string;
    country_code: string | null;
    method: string;
    content_length: number | null;
    status_code: number | null;
    user_agent: string | null;
    mime_type: string | null;
    request_url: string | null;
    referer_url: string | null;
    origin_url: string | null;
    user_id: number | null;
    geo_country_code: string | null;
    geo_lat: number | null;
    geo_lon: number | null;
    is_bot: boolean | null;
    is_bot_by_frequency: boolean;
    is_bot_by_user_agent: boolean;
    is_bot_by_parameters: boolean;
    bot_detection_metadata: string | null;
    created_at: string;
    updated_at: string;
}

interface PaginatedRequests {
    data: RequestLog[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Props {
    requests: PaginatedRequests;
    filters: {
        search: string | null;
        per_page: number;
        is_bot: string;
        include_user_agents: string[];
        exclude_user_agents: string[];
        include_ips: string[];
        exclude_ips: string[];
        date_from: string | null;
        date_to: string | null;
        exclude_connected_users_ips: boolean;
    };
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search || '');
const perPage = ref(props.filters.per_page.toString());
const isBotFilter = ref(props.filters.is_bot || 'all');
const includeUserAgents = ref(props.filters.include_user_agents?.join('\n') || '');
const excludeUserAgents = ref(props.filters.exclude_user_agents?.join('\n') || '');
const includeIps = ref(props.filters.include_ips?.join('\n') || '');
const excludeIps = ref(props.filters.exclude_ips?.join('\n') || '');
const dateFrom = ref(props.filters.date_from || '');
const dateTo = ref(props.filters.date_to || '');
const excludeConnectedUsersIps = ref(props.filters.exclude_connected_users_ips);

// Sélection de requêtes
const selectedRequests = ref<Set<number>>(new Set());
const lastSelectedIndex = ref<number | null>(null);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Logs des requêtes',
        href: route('dashboard.request-logs.index', undefined, false),
    },
];

const formatDate = (dateString: string) => {
    return format(new Date(dateString), 'dd/MM/yyyy HH:mm:ss', { locale: fr });
};

const getStatusBadgeVariant = (statusCode: number | null) => {
    if (!statusCode) return 'secondary';
    if (statusCode >= 200 && statusCode < 300) return 'default';
    if (statusCode >= 300 && statusCode < 400) return 'secondary';
    if (statusCode >= 400 && statusCode < 500) return 'destructive';
    if (statusCode >= 500) return 'destructive';
    return 'secondary';
};

const getMethodBadgeVariant = (method: string) => {
    switch (method) {
        case 'GET':
            return 'default';
        case 'POST':
            return 'secondary';
        case 'PUT':
            return 'outline';
        case 'PATCH':
            return 'outline';
        case 'DELETE':
            return 'destructive';
        default:
            return 'secondary';
    }
};

const truncateUrl = (url: string | null, maxLength: number = 50) => {
    if (!url) return '-';
    return url.length > maxLength ? `${url.substring(0, maxLength)}...` : url;
};

const truncateUserAgent = (userAgent: string | null, maxLength: number = 60) => {
    if (!userAgent) return '-';
    return userAgent.length > maxLength ? `${userAgent.substring(0, maxLength)}...` : userAgent;
};

const search = () => {
    const includeUAArray = includeUserAgents.value.split('\n').filter((ua) => ua.trim());
    const excludeUAArray = excludeUserAgents.value.split('\n').filter((ua) => ua.trim());
    const includeIpArray = includeIps.value.split('\n').filter((ip) => ip.trim());
    const excludeIpArray = excludeIps.value.split('\n').filter((ip) => ip.trim());

    const params = {
        search: searchQuery.value || undefined,
        per_page: perPage.value,
        is_bot: isBotFilter.value !== 'all' ? isBotFilter.value : undefined,
        include_user_agents: includeUAArray.length > 0 ? includeUAArray : undefined,
        exclude_user_agents: excludeUAArray.length > 0 ? excludeUAArray : undefined,
        include_ips: includeIpArray.length > 0 ? includeIpArray : undefined,
        exclude_ips: excludeIpArray.length > 0 ? excludeIpArray : undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        exclude_connected_users_ips: excludeConnectedUsersIps.value === true ? '1' : undefined,
    };
    router.get(route('dashboard.request-logs.index'), params, {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    searchQuery.value = '';
    perPage.value = '15';
    isBotFilter.value = 'all';
    includeUserAgents.value = '';
    excludeUserAgents.value = '';
    includeIps.value = '';
    excludeIps.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    excludeConnectedUsersIps.value = false;
    search();
};

const changePage = (page: number) => {
    const includeUAArray = includeUserAgents.value.split('\n').filter((ua) => ua.trim());
    const excludeUAArray = excludeUserAgents.value.split('\n').filter((ua) => ua.trim());
    const includeIpArray = includeIps.value.split('\n').filter((ip) => ip.trim());
    const excludeIpArray = excludeIps.value.split('\n').filter((ip) => ip.trim());

    router.get(
        route('dashboard.request-logs.index'),
        {
            page,
            search: searchQuery.value || undefined,
            per_page: perPage.value,
            is_bot: isBotFilter.value !== 'all' ? isBotFilter.value : undefined,
            include_user_agents: includeUAArray.length > 0 ? includeUAArray : undefined,
            exclude_user_agents: excludeUAArray.length > 0 ? excludeUAArray : undefined,
            include_ips: includeIpArray.length > 0 ? includeIpArray : undefined,
            exclude_ips: excludeIpArray.length > 0 ? excludeIpArray : undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
            exclude_connected_users_ips: excludeConnectedUsersIps.value === true ? '1' : undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

watch([perPage], () => {
    search();
});

// Watcher pour excludeConnectedUsersIps
watch(excludeConnectedUsersIps, (newValue, oldValue) => {
    // Vérifier si la valeur a vraiment changé
    if (newValue !== oldValue) {
        search();
    }
});

const paginationInfo = computed(() => {
    const { from, to, total } = props.requests;
    return `Affichage de ${from} à ${to} sur ${total} résultats`;
});

const isDetectedAsBot = (request: RequestLog): boolean => {
    return request.is_bot || request.is_bot_by_frequency || request.is_bot_by_user_agent || request.is_bot_by_parameters;
};

const getBotDetectionReasons = (request: RequestLog): string[] => {
    const reasons: string[] = [];

    // Check old detection
    if (request.is_bot) {
        reasons.push('Détecté comme bot (analyse ancienne)');
    }

    // Check new detections
    if (request.is_bot_by_frequency) {
        reasons.push('Fréquence de requêtes élevée');
    }
    if (request.is_bot_by_user_agent) {
        reasons.push('User-Agent suspect');
    }
    if (request.is_bot_by_parameters) {
        reasons.push('Paramètres URL suspects');
    }

    // Parse metadata for more details
    if (request.bot_detection_metadata) {
        try {
            const metadata = JSON.parse(request.bot_detection_metadata);
            if (metadata.reasons && Array.isArray(metadata.reasons)) {
                metadata.reasons.forEach((reason: string) => {
                    if (!reasons.includes(reason)) {
                        reasons.push(reason);
                    }
                });
            }
            if (metadata.skipped && metadata.reason) {
                reasons.push(metadata.reason);
            }
        } catch (e) {
            console.error('Erreur lors de la parsing des métadonnées bot:', e);
        }
    }

    return reasons;
};

// Fonctions de sélection
const toggleSelection = (requestId: number, index: number, event: MouseEvent) => {
    if (event.shiftKey && lastSelectedIndex.value !== null) {
        // Sélection multiple avec Shift
        const start = Math.min(lastSelectedIndex.value, index);
        const end = Math.max(lastSelectedIndex.value, index);

        for (let i = start; i <= end; i++) {
            if (props.requests.data[i]) {
                selectedRequests.value.add(props.requests.data[i].id);
            }
        }
    } else {
        // Sélection simple
        if (selectedRequests.value.has(requestId)) {
            selectedRequests.value.delete(requestId);
        } else {
            selectedRequests.value.add(requestId);
        }
        lastSelectedIndex.value = index;
    }

    // Forcer la réactivité
    selectedRequests.value = new Set(selectedRequests.value);
};

const toggleSelectAll = () => {
    if (selectedRequests.value.size === props.requests.data.length) {
        selectedRequests.value.clear();
    } else {
        props.requests.data.forEach((request) => {
            selectedRequests.value.add(request.id);
        });
    }
    selectedRequests.value = new Set(selectedRequests.value);
};

const isAllSelected = computed(() => {
    return props.requests.data.length > 0 && selectedRequests.value.size === props.requests.data.length;
});

const isIndeterminate = computed(() => {
    return selectedRequests.value.size > 0 && selectedRequests.value.size < props.requests.data.length;
});

// Fonction pour marquer les requêtes sélectionnées comme bot
const markSelectedAsBot = async () => {
    if (selectedRequests.value.size === 0) {
        return;
    }

    try {
        await axios.post(route('dashboard.request-logs.mark-as-bot'), {
            request_ids: Array.from(selectedRequests.value),
        });

        // Rafraîchir la page
        router.reload({
            preserveState: true,
            preserveScroll: true,
        });

        // Vider la sélection
        selectedRequests.value.clear();
    } catch (error) {
        console.error('Erreur lors du marquage comme bot:', error);
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Logs des requêtes" />

        <div class="px-5 py-6">
            <div class="flex items-center justify-between">
                <Heading title="Logs des requêtes" description="Visualisez toutes les requêtes HTTP enregistrées avec leurs métadonnées complètes." />
            </div>

            <!-- Filtres -->
            <Card class="mt-6">
                <CardHeader>
                    <CardTitle>Filtres</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-6">
                        <!-- Main filters row -->
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div class="md:col-span-2">
                                <Label for="search">Rechercher</Label>
                                <div class="relative">
                                    <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                    <Input
                                        id="search"
                                        v-model="searchQuery"
                                        placeholder="IP, URL, User-Agent, Méthode, Status..."
                                        class="pl-10"
                                        @keyup.enter="search"
                                    />
                                </div>
                            </div>

                            <div>
                                <Label for="per_page">Éléments par page</Label>
                                <Select v-model="perPage">
                                    <SelectTrigger>
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

                            <div>
                                <Label for="is_bot">Type de visiteur</Label>
                                <Select v-model="isBotFilter">
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Tous</SelectItem>
                                        <SelectItem value="false">Humains seulement</SelectItem>
                                        <SelectItem value="true">Bots seulement</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <!-- Date filters -->
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div>
                                <Label for="date_from">Date de début</Label>
                                <Input id="date_from" v-model="dateFrom" type="date" placeholder="Date de début" />
                            </div>

                            <div>
                                <Label for="date_to">Date de fin</Label>
                                <Input id="date_to" v-model="dateTo" type="date" placeholder="Date de fin" />
                            </div>

                            <div class="md:col-span-2">
                                <Label>Options de filtrage</Label>
                                <div class="mt-2 flex items-center space-x-2">
                                    <Switch id="exclude_connected_users" v-model="excludeConnectedUsersIps" />
                                    <Label for="exclude_connected_users" class="cursor-pointer font-normal">
                                        Exclure les IP des utilisateurs connectés
                                    </Label>
                                </div>
                            </div>
                        </div>

                        <!-- User agents and IPs filters -->
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <Label for="include_user_agents">User-Agents à inclure</Label>
                                <Textarea
                                    id="include_user_agents"
                                    v-model="includeUserAgents"
                                    placeholder="Un par ligne (recherche partielle)"
                                    rows="3"
                                    class="text-sm"
                                />
                            </div>

                            <div>
                                <Label for="exclude_user_agents">User-Agents à exclure</Label>
                                <Textarea
                                    id="exclude_user_agents"
                                    v-model="excludeUserAgents"
                                    placeholder="Un par ligne (recherche partielle)"
                                    rows="3"
                                    class="text-sm"
                                />
                            </div>

                            <div>
                                <Label for="include_ips">IPs à inclure</Label>
                                <Textarea
                                    id="include_ips"
                                    v-model="includeIps"
                                    placeholder="Une par ligne (correspondance exacte)"
                                    rows="3"
                                    class="text-sm"
                                />
                            </div>

                            <div>
                                <Label for="exclude_ips">IPs à exclure</Label>
                                <Textarea
                                    id="exclude_ips"
                                    v-model="excludeIps"
                                    placeholder="Une par ligne (correspondance exacte)"
                                    rows="3"
                                    class="text-sm"
                                />
                            </div>
                        </div>

                        <!-- Action buttons -->
                        <div class="flex items-center justify-end space-x-2">
                            <Button variant="outline" @click="resetFilters">
                                <RotateCcw class="mr-2 h-4 w-4" />
                                Réinitialiser
                            </Button>
                            <Button @click="search">
                                <Search class="mr-2 h-4 w-4" />
                                Rechercher
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Actions de sélection -->
            <div v-if="selectedRequests.size > 0" class="bg-muted mt-4 flex items-center justify-between rounded-lg p-4">
                <div class="text-muted-foreground text-sm">{{ selectedRequests.size }} requête(s) sélectionnée(s)</div>
                <Button size="sm" @click="markSelectedAsBot">
                    <Flag class="mr-2 h-4 w-4" />
                    Marquer comme bot
                </Button>
            </div>

            <!-- Tableau des requêtes -->
            <Card class="mt-6">
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-12">
                                        <Checkbox :checked="isAllSelected" :indeterminate="isIndeterminate" @update:checked="toggleSelectAll" />
                                    </TableHead>
                                    <TableHead>Date</TableHead>
                                    <TableHead>IP</TableHead>
                                    <TableHead>Pays</TableHead>
                                    <TableHead>Méthode</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>URL</TableHead>
                                    <TableHead>Referer</TableHead>
                                    <TableHead>User-Agent</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Taille</TableHead>
                                    <TableHead>Bot</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="(request, index) in props.requests.data" :key="request.id">
                                    <TableCell>
                                        <Checkbox
                                            :checked="selectedRequests.has(request.id)"
                                            @click="(event: MouseEvent) => toggleSelection(request.id, index, event)"
                                        />
                                    </TableCell>
                                    <TableCell class="font-medium">
                                        {{ formatDate(request.created_at) }}
                                    </TableCell>

                                    <TableCell>
                                        <div class="flex items-center">
                                            <Globe class="text-muted-foreground mr-2 h-4 w-4" />
                                            {{ request.ip_address }}
                                        </div>
                                    </TableCell>

                                    <TableCell>
                                        <Badge v-if="request.geo_country_code" variant="outline">
                                            {{ request.geo_country_code }}
                                        </Badge>
                                        <span v-else class="text-muted-foreground">-</span>
                                    </TableCell>

                                    <TableCell>
                                        <Badge :variant="getMethodBadgeVariant(request.method)">
                                            {{ request.method }}
                                        </Badge>
                                    </TableCell>

                                    <TableCell>
                                        <Badge v-if="request.status_code" :variant="getStatusBadgeVariant(request.status_code)">
                                            {{ request.status_code }}
                                        </Badge>
                                        <span v-else class="text-muted-foreground">-</span>
                                    </TableCell>

                                    <TableCell>
                                        <span v-if="request.request_url" :title="request.request_url" class="text-sm">
                                            {{ truncateUrl(request.request_url) }}
                                        </span>
                                        <span v-else class="text-muted-foreground">-</span>
                                    </TableCell>

                                    <TableCell>
                                        <span v-if="request.referer_url" :title="request.referer_url" class="text-muted-foreground text-sm">
                                            {{ truncateUrl(request.referer_url, 40) }}
                                        </span>
                                        <span v-else class="text-muted-foreground">-</span>
                                    </TableCell>

                                    <TableCell>
                                        <span v-if="request.user_agent" :title="request.user_agent" class="text-muted-foreground text-sm">
                                            {{ truncateUserAgent(request.user_agent) }}
                                        </span>
                                        <span v-else class="text-muted-foreground">-</span>
                                    </TableCell>

                                    <TableCell>
                                        <span v-if="request.mime_type" class="text-sm">
                                            {{ request.mime_type }}
                                        </span>
                                        <span v-else class="text-muted-foreground">-</span>
                                    </TableCell>

                                    <TableCell>
                                        <span v-if="request.content_length" class="text-sm"> {{ request.content_length }} b </span>
                                        <span v-else class="text-muted-foreground">-</span>
                                    </TableCell>

                                    <TableCell>
                                        <TooltipProvider>
                                            <Tooltip v-if="isDetectedAsBot(request)">
                                                <TooltipTrigger as-child>
                                                    <div class="flex cursor-help items-center">
                                                        <Bot class="h-4 w-4 text-orange-500" />
                                                    </div>
                                                </TooltipTrigger>
                                                <TooltipContent side="left" class="max-w-xs">
                                                    <div class="space-y-1">
                                                        <p class="font-semibold">Raisons de détection:</p>
                                                        <ul class="list-inside list-disc text-sm">
                                                            <li v-for="(reason, index) in getBotDetectionReasons(request)" :key="index">
                                                                {{ reason }}
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </TooltipContent>
                                            </Tooltip>
                                            <div v-else-if="request.user_id" class="flex items-center" title="Utilisateur connecté">
                                                <User class="h-4 w-4 text-green-500" />
                                            </div>
                                            <div v-else class="flex items-center" title="Visiteur humain">
                                                <User class="h-4 w-4 text-blue-500" />
                                            </div>
                                        </TooltipProvider>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <!-- Pagination -->
            <div class="mt-6 flex items-center justify-between">
                <div class="text-muted-foreground text-sm">
                    {{ paginationInfo }}
                </div>

                <div class="flex items-center space-x-2">
                    <Button variant="outline" :disabled="props.requests.current_page === 1" @click="changePage(props.requests.current_page - 1)">
                        <ChevronLeft class="h-4 w-4" />
                        Précédent
                    </Button>

                    <div class="flex items-center space-x-1">
                        <span class="text-sm"> Page {{ props.requests.current_page }} sur {{ props.requests.last_page }} </span>
                    </div>

                    <Button
                        variant="outline"
                        :disabled="props.requests.current_page === props.requests.last_page"
                        @click="changePage(props.requests.current_page + 1)"
                    >
                        Suivant
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
