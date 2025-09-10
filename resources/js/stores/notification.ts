import type { Notification } from '@/types/notification';
import axios from 'axios';
import { defineStore } from 'pinia';
import { computed, ref } from 'vue';

export const useNotificationStore = defineStore('notification', () => {
    // State
    const notifications = ref<Notification[]>([]);
    const toastNotifications = ref<Notification[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);

    // Computed
    const unreadCount = computed(() => notifications.value.filter((n) => !n.is_read).length);

    const unreadNotifications = computed(() => notifications.value.filter((n) => !n.is_read));

    const readNotifications = computed(() => notifications.value.filter((n) => n.is_read));

    // Actions
    const fetchNotifications = async () => {
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.get('/dashboard/api/notifications');
            // Handle both paginated and non-paginated responses
            if (response.data.data) {
                // Check if data is an array (non-paginated) or object with data property (paginated)
                if (Array.isArray(response.data.data)) {
                    notifications.value = response.data.data;
                } else if (response.data.data.data && Array.isArray(response.data.data.data)) {
                    // Paginated response
                    notifications.value = response.data.data.data;
                } else {
                    // Fallback to empty array if structure is unexpected
                    console.warn('Unexpected notification response structure:', response.data);
                    notifications.value = [];
                }
            } else {
                // Fallback to empty array if no data
                notifications.value = [];
            }
        } catch (err) {
            console.error('Failed to fetch notifications:', err);
            error.value = 'Failed to fetch notifications';
            // Ensure notifications is always an array even on error
            notifications.value = [];
        } finally {
            loading.value = false;
        }
    };

    const markAsRead = async (id: number) => {
        try {
            await axios.put(`/dashboard/api/notifications/${id}/read`);
            const notification = notifications.value.find((n) => n.id === id);
            if (notification) {
                notification.is_read = true;
                notification.read_at = new Date().toISOString();
            }
        } catch (err) {
            console.error('Failed to mark notification as read:', err);
            showToast('error', 'Error', 'Failed to mark notification as read');
        }
    };

    const markAllAsRead = async () => {
        try {
            await axios.put('/dashboard/api/notifications/read-all');
            notifications.value.forEach((notification) => {
                if (!notification.is_read) {
                    notification.is_read = true;
                    notification.read_at = new Date().toISOString();
                }
            });
            showToast('success', 'Success', 'All notifications marked as read');
        } catch (err) {
            console.error('Failed to mark all notifications as read:', err);
            showToast('error', 'Error', 'Failed to mark all notifications as read');
        }
    };

    const dismiss = async (id: number) => {
        try {
            await axios.delete(`/dashboard/api/notifications/${id}`);
            notifications.value = notifications.value.filter((n) => n.id !== id);
            showToast('success', 'Success', 'Notification dismissed');
        } catch (err) {
            console.error('Failed to dismiss notification:', err);
            showToast('error', 'Error', 'Failed to dismiss notification');
        }
    };

    const clearAll = async () => {
        try {
            await axios.delete('/dashboard/api/notifications/clear');

            notifications.value = notifications.value.filter((n) => n.is_persistent);
            showToast('success', 'Success', 'All notifications cleared');
        } catch (err) {
            console.error('Failed to clear notifications:', err);
            showToast('error', 'Error', 'Failed to clear notifications');
        }
    };

    const showToast = (type: string, title: string, message: string) => {
        const toast: Notification = {
            id: Date.now(),
            type,
            title,
            message,
            is_read: false,
            is_persistent: false,
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString(),
        };

        toastNotifications.value.push(toast);

        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            dismissToast(toast.id);
        }, 5000);
    };

    const dismissToast = (id: number) => {
        toastNotifications.value = toastNotifications.value.filter((n) => n.id !== id);
    };

    // WebSocket / Polling support
    const startPolling = (interval = 30000) => {
        setInterval(() => {
            fetchNotifications();
        }, interval);
    };

    // SSE (Server-Sent Events) support
    const connectSSE = () => {
        const eventSource = new EventSource('/dashboard/api/notifications/stream');

        eventSource.onmessage = (event) => {
            const notification = JSON.parse(event.data);
            addNotification(notification);
        };

        eventSource.onerror = (error) => {
            console.error('SSE connection error:', error);
            eventSource.close();
            // Fallback to polling
            startPolling();
        };

        return eventSource;
    };

    const addNotification = (notification: Notification) => {
        // Check if notification already exists
        const exists = notifications.value.find((n) => n.id === notification.id);
        if (!exists) {
            notifications.value.unshift(notification);

            // Show as toast if it's new and unread
            if (!notification.is_read) {
                toastNotifications.value.push(notification);

                // Auto-dismiss toast after 5 seconds
                setTimeout(() => {
                    dismissToast(notification.id);
                }, 5000);
            }
        }
    };

    const updateNotification = (notification: Notification) => {
        const index = notifications.value.findIndex((n) => n.id === notification.id);
        if (index !== -1) {
            notifications.value[index] = notification;
        }
    };

    const removeNotification = (id: number) => {
        notifications.value = notifications.value.filter((n) => n.id !== id);
        toastNotifications.value = toastNotifications.value.filter((n) => n.id !== id);
    };

    return {
        // State
        notifications,
        toastNotifications,
        loading,
        error,

        // Computed
        unreadCount,
        unreadNotifications,
        readNotifications,

        // Actions
        fetchNotifications,
        markAsRead,
        markAllAsRead,
        dismiss,
        clearAll,
        showToast,
        dismissToast,
        startPolling,
        connectSSE,
        addNotification,
        updateNotification,
        removeNotification,
    };
});
