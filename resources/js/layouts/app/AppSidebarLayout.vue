<script setup lang="ts">
import AppContent from '@/components/dashboard/AppContent.vue';
import AppShell from '@/components/dashboard/AppShell.vue';
import AppSidebar from '@/components/dashboard/AppSidebar.vue';
import AppSidebarHeader from '@/components/dashboard/AppSidebarHeader.vue';
import NotificationToast from '@/components/ui/notification/NotificationToast.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <Toaster />
        <NotificationToast />
        <AppSidebar />
        <AppContent variant="sidebar">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
    </AppShell>
</template>
