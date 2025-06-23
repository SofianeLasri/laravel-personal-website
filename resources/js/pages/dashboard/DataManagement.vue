<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { AlertTriangle, Download, Info, Trash2, Upload } from 'lucide-vue-next';
import { computed, onUnmounted, ref } from 'vue';

interface Props {
    exportTables: string[];
    importTables: string[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Data Management',
        href: '/dashboard/data-management',
    },
];

// State management
const isExporting = ref(false);
const exportProgress = ref(0);
const exportRequestId = ref<string | null>(null);
const exportStatusInterval = ref<number | null>(null);
const exportDownloadUrl = ref<string | null>(null);
const isImporting = ref(false);
const importProgress = ref(0);
const importFile = ref<File | null>(null);
const uploadedFilePath = ref<string | null>(null);
const importMetadata = ref<any>(null);
const importStats = ref<any>(null);
const error = ref<string | null>(null);
const success = ref<string | null>(null);

const fileInput = ref<HTMLInputElement>();

// Computed properties
const hasImportFile = computed(() => importFile.value !== null);
const canImport = computed(() => uploadedFilePath.value !== null && importMetadata.value !== null);

// Export functionality
const handleExport = async () => {
    isExporting.value = true;
    exportProgress.value = 0;
    error.value = null;
    success.value = null;
    exportRequestId.value = null;
    exportDownloadUrl.value = null;

    try {
        const response = await axios.post('/dashboard/data-management/export');

        if (response.status === 202) {
            // Export accepted, start polling for status
            exportRequestId.value = response.data.request_id;
            startExportStatusPolling();
        } else if (response.status === 409) {
            // Export already in progress
            error.value = response.data.message;
            isExporting.value = false;
        }
    } catch (err: any) {
        error.value = err.response?.data?.message || 'Failed to start export. Please try again.';
        isExporting.value = false;
    }
};

const startExportStatusPolling = () => {
    if (!exportRequestId.value) return;

    exportStatusInterval.value = setInterval(async () => {
        try {
            const response = await axios.get(`/dashboard/data-management/export/${exportRequestId.value}/status`);
            const status = response.data;

            switch (status.status) {
                case 'queued':
                    exportProgress.value = 10;
                    break;
                case 'processing':
                    exportProgress.value = 50;
                    break;
                case 'completed':
                    exportProgress.value = 100;
                    exportDownloadUrl.value = status.download_url;
                    success.value = 'Export completed successfully! Click the download button to get your file.';
                    stopExportStatusPolling();
                    isExporting.value = false;
                    break;
                case 'failed':
                    error.value = status.error || 'Export failed. Please try again.';
                    stopExportStatusPolling();
                    isExporting.value = false;
                    break;
                case 'not_found':
                    error.value = 'Export request not found or expired.';
                    stopExportStatusPolling();
                    isExporting.value = false;
                    break;
            }
        } catch (err: any) {
            error.value = 'Failed to check export status. Please refresh the page.';
            stopExportStatusPolling();
            isExporting.value = false;
            console.error(err);
        }
    }, 2000); // Poll every 2 seconds
};

const stopExportStatusPolling = () => {
    if (exportStatusInterval.value) {
        clearInterval(exportStatusInterval.value);
        exportStatusInterval.value = null;
    }
};

const downloadExport = () => {
    if (exportDownloadUrl.value) {
        window.location.href = exportDownloadUrl.value;
    }
};

// Import functionality
const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (file) {
        importFile.value = file;
        error.value = null;
        success.value = null;
        uploadedFilePath.value = null;
        importMetadata.value = null;
        importStats.value = null;
    }
};

const uploadImportFile = async () => {
    if (!importFile.value) return;

    const formData = new FormData();
    formData.append('import_file', importFile.value);

    try {
        const response = await axios.post('/dashboard/data-management/upload', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        uploadedFilePath.value = response.data.file_path;
        importMetadata.value = response.data.metadata;
        success.value = 'File uploaded and validated successfully!';
    } catch (err: any) {
        error.value = err.response?.data?.message || 'Upload failed. Please check your file and try again.';
        if (err.response?.data?.errors?.import_file) {
            error.value = err.response.data.errors.import_file.join(', ');
        }
    }
};

const handleImport = async () => {
    if (!uploadedFilePath.value) return;

    isImporting.value = true;
    importProgress.value = 0;
    error.value = null;
    success.value = null;

    // Simulate progress
    const progressInterval = setInterval(() => {
        if (importProgress.value < 90) {
            importProgress.value += 5;
        }
    }, 300);

    try {
        const response = await axios.post('/dashboard/data-management/import', {
            file_path: uploadedFilePath.value,
            confirm_import: true,
        });

        clearInterval(progressInterval);
        importProgress.value = 100;

        importStats.value = response.data.stats;
        success.value = 'Import completed successfully! The website has been restored.';

        // Clear import state
        importFile.value = null;
        uploadedFilePath.value = null;
        importMetadata.value = null;
        if (fileInput.value) {
            fileInput.value.value = '';
        }
    } catch (err: any) {
        clearInterval(progressInterval);
        error.value = err.response?.data?.message || 'Import failed. Please try again.';
    } finally {
        isImporting.value = false;
        importProgress.value = 0;
    }
};

const cancelImport = async () => {
    if (!uploadedFilePath.value) return;

    try {
        await axios.delete('/dashboard/data-management/cancel', {
            data: { file_path: uploadedFilePath.value },
        });

        // Clear import state
        importFile.value = null;
        uploadedFilePath.value = null;
        importMetadata.value = null;
        if (fileInput.value) {
            fileInput.value.value = '';
        }

        success.value = 'Import cancelled successfully.';
    } catch (err: any) {
        error.value = 'Failed to cancel import.';
        console.error(err);
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleString();
};

const formatFileSize = (bytes: number) => {
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    if (bytes === 0) return '0 Bytes';
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return Math.round((bytes / Math.pow(1024, i)) * 100) / 100 + ' ' + sizes[i];
};

// Cleanup on component unmount
onUnmounted(() => {
    stopExportStatusPolling();
});
</script>

<template>
    <Head title="Data Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Alerts -->
            <Alert v-if="error" variant="destructive">
                <AlertTriangle class="h-4 w-4" />
                <AlertTitle>Error</AlertTitle>
                <AlertDescription>{{ error }}</AlertDescription>
            </Alert>

            <Alert v-if="success" class="border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-400">
                <Info class="h-4 w-4" />
                <AlertTitle>Success</AlertTitle>
                <AlertDescription>{{ success }}</AlertDescription>
            </Alert>

            <!-- Warning Alert -->
            <Alert variant="destructive">
                <AlertTriangle class="h-4 w-4" />
                <AlertTitle>Warning</AlertTitle>
                <AlertDescription>
                    Import will completely replace all existing website content and files. This action cannot be undone. Please ensure you have a
                    backup before proceeding.
                </AlertDescription>
            </Alert>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Export Section -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Download class="h-5 w-5" />
                            Export Website Data
                        </CardTitle>
                        <CardDescription> Export all website content including database records and uploaded files to a ZIP file. </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label>Export includes:</Label>
                            <div class="text-muted-foreground text-sm">
                                <p>• All database tables ({{ props.exportTables.length }} tables)</p>
                                <p>• All uploaded files and images</p>
                                <p>• Export metadata and timestamp</p>
                            </div>
                        </div>

                        <Separator />

                        <div v-if="isExporting" class="space-y-2">
                            <Label>Export progress</Label>
                            <Progress :value="exportProgress" class="w-full" />
                            <p class="text-muted-foreground text-sm">
                                {{
                                    exportProgress <= 10
                                        ? 'Queued for processing...'
                                        : exportProgress <= 50
                                          ? 'Processing export...'
                                          : 'Finalizing export...'
                                }}
                                {{ exportProgress }}%
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Button @click="handleExport" :disabled="isExporting" class="w-full">
                                <Download class="mr-2 h-4 w-4" />
                                {{ isExporting ? 'Exporting...' : 'Export Website Data' }}
                            </Button>

                            <Button v-if="exportDownloadUrl" @click="downloadExport" variant="outline" class="w-full">
                                <Download class="mr-2 h-4 w-4" />
                                Download Export File
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Import Section -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Upload class="h-5 w-5" />
                            Import Website Data
                        </CardTitle>
                        <CardDescription>
                            Import website data from a previously exported ZIP file. This will replace all existing content.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- File Selection -->
                        <div class="space-y-2">
                            <Label for="import-file">Select import file</Label>
                            <Input ref="fileInput" id="import-file" type="file" accept=".zip" @change="handleFileSelect" :disabled="isImporting" />
                            <p class="text-muted-foreground text-sm">Select a ZIP file exported from this website.</p>
                        </div>

                        <!-- Upload Button -->
                        <Button v-if="hasImportFile && !uploadedFilePath" @click="uploadImportFile" variant="outline" class="w-full">
                            <Upload class="mr-2 h-4 w-4" />
                            Upload and Validate File
                        </Button>

                        <!-- Import Metadata -->
                        <div v-if="importMetadata" class="space-y-3">
                            <Separator />

                            <div class="space-y-2">
                                <Label>Import file information</Label>
                                <div class="rounded-md border p-3 text-sm">
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <span class="font-medium">Export date:</span>
                                            {{ formatDate(importMetadata.export_date) }}
                                        </div>
                                        <div>
                                            <span class="font-medium">Laravel version:</span>
                                            {{ importMetadata.laravel_version }}
                                        </div>
                                        <div>
                                            <span class="font-medium">Database:</span>
                                            {{ importMetadata.database_name }}
                                        </div>
                                        <div>
                                            <span class="font-medium">Files:</span>
                                            {{ importMetadata.files_count }} files
                                        </div>
                                        <div class="col-span-2">
                                            <span class="font-medium">File size:</span>
                                            {{ importFile ? formatFileSize(importFile.size) : 'Unknown' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Import Progress -->
                            <div v-if="isImporting" class="space-y-2">
                                <Label>Import progress</Label>
                                <Progress :value="importProgress" class="w-full" />
                                <p class="text-muted-foreground text-sm">Importing data... {{ importProgress }}%</p>
                            </div>

                            <!-- Import Actions -->
                            <div class="flex gap-2">
                                <Button @click="handleImport" :disabled="!canImport || isImporting" variant="destructive" class="flex-1">
                                    <Upload class="mr-2 h-4 w-4" />
                                    {{ isImporting ? 'Importing...' : 'Import Data' }}
                                </Button>

                                <Button @click="cancelImport" :disabled="isImporting" variant="outline">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Import Statistics -->
            <Card v-if="importStats">
                <CardHeader>
                    <CardTitle>Import Statistics</CardTitle>
                    <CardDescription> Summary of the last successful import operation. </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold">{{ importStats.tables_imported }}</div>
                            <div class="text-muted-foreground text-sm">Tables</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold">{{ importStats.records_imported }}</div>
                            <div class="text-muted-foreground text-sm">Records</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold">{{ importStats.files_imported }}</div>
                            <div class="text-muted-foreground text-sm">Files</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold">{{ formatDate(importStats.import_date) }}</div>
                            <div class="text-muted-foreground text-sm">Import Date</div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Tables Information -->
            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Export Tables</CardTitle>
                        <CardDescription> Database tables that will be included in the export. </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Table Name</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="table in props.exportTables" :key="table">
                                    <TableCell class="font-mono text-sm">{{ table }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Import Tables</CardTitle>
                        <CardDescription> Database tables that will be restored during import. </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Table Name</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="table in props.importTables" :key="table">
                                    <TableCell class="font-mono text-sm">{{ table }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
