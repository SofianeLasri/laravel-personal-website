<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
            <div class="space-y-6">
                <!-- Header -->
                <div>
                    <h1 class="text-2xl font-bold">Notifications</h1>
                    <p class="text-muted-foreground">Manage and view all your notifications</p>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Button variant="outline" @click="notificationStore.markAllAsRead" :disabled="unreadCount === 0"> Mark all as read </Button>
                        <Button variant="outline" @click="notificationStore.clearAll" :disabled="notifications.length === 0"> Clear all </Button>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button variant="default" @click="testNotification('success')"> Test Success </Button>
                        <Button variant="destructive" @click="testNotification('error')"> Test Error </Button>
                        <Button variant="outline" @click="testNotification('warning')"> Test Warning </Button>
                        <Button variant="secondary" @click="testNotification('info')"> Test Info </Button>
                    </div>
                </div>

                <!-- Notifications List -->
                <Card>
                    <CardHeader>
                        <CardTitle>All Notifications</CardTitle>
                        <CardDescription> You have {{ unreadCount }} unread notification{{ unreadCount !== 1 ? 's' : '' }} </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <NotificationList :notifications="notifications" @mark-as-read="handleMarkAsRead" @dismiss="handleDismiss" />
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import NotificationList from '@/components/ui/notification/NotificationList.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useNotificationStore } from '@/stores/notification';
import axios from 'axios';
import { computed, onMounted } from 'vue';

const notificationStore = useNotificationStore();

const breadcrumbs = [{ title: 'Dashboard', href: '/dashboard' }, { title: 'Notifications' }];

const notifications = computed(() => notificationStore.notifications);
const unreadCount = computed(() => notificationStore.unreadCount);

onMounted(() => {
    notificationStore.fetchNotifications();
});

const handleMarkAsRead = async (id: number) => {
    await notificationStore.markAsRead(id);
};

const handleDismiss = async (id: number) => {
    await notificationStore.dismiss(id);
};

const testNotification = async (type: string) => {
    try {
        await axios.post('/dashboard/api/notifications', {
            type,
            title: `Test ${type} notification`,
            message: `This is a test ${type} notification message to verify the system is working correctly.`,
            data: {
                test: true,
                timestamp: new Date().toISOString(),
            },
        });

        // Refresh notifications
        await notificationStore.fetchNotifications();

        // Show toast
        notificationStore.showToast('success', 'Notification Created', `A test ${type} notification has been created`);
    } catch (error) {
        notificationStore.showToast('error', 'Error', 'Failed to create test notification');
    }
};
</script>
