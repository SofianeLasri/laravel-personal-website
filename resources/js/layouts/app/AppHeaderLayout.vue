<script setup lang="ts">
import AppContent from '@/components/dashboard/shared/layout/AppContent.vue';
import AppHeader from '@/components/dashboard/shared/layout/AppHeader.vue';
import AppShell from '@/components/dashboard/shared/layout/AppShell.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell class="flex-col">
        <AppHeader :breadcrumbs="breadcrumbs" />
        <AppContent>
            <slot />
        </AppContent>
    </AppShell>
</template>
