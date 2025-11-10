<template>
  <Popover>
    <PopoverTrigger as-child>
      <Button variant="ghost" size="icon" class="relative" data-testid="notification-bell">
        <Bell class="h-5 w-5" />
        <span
          data-testid="notification-count"
          :class="unreadCount > 0 ? 'absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs text-white' : 'sr-only'"
        >
          {{ unreadCount > 99 ? '99+' : unreadCount }}
        </span>
      </Button>
    </PopoverTrigger>
    
    <PopoverContent class="w-96 p-0" align="end" data-testid="notification-popup">
      <div class="flex items-center justify-between border-b px-4 py-3">
        <h3 class="font-semibold">Notifications</h3>
        
        <div class="flex items-center gap-1">
          <Button
            v-if="unreadCount > 0"
            variant="ghost"
            size="sm"
            @click="markAllAsRead"
            :disabled="loading"
            data-testid="mark-all-read"
          >
            Mark all as read
          </Button>
          
          <Button
            variant="ghost"
            size="sm"
            @click="clearAll"
            :disabled="loading || notifications.length === 0"
          >
            Clear all
          </Button>
        </div>
      </div>
      
      <div class="border-b px-4 py-2">
        <select
          v-model="severityFilter"
          data-testid="severity-filter"
          class="w-full rounded-md border border-input bg-background px-3 py-1 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
        >
          <option value="">All severities</option>
          <option value="info">Info</option>
          <option value="warning">Warning</option>
          <option value="error">Error</option>
          <option value="critical">Critical</option>
        </select>
      </div>
      
      <ScrollArea class="h-[400px]">
        <div class="p-4">
          <NotificationList
            :notifications="filteredNotifications"
            @mark-as-read="handleMarkAsRead"
            @dismiss="handleDismiss"
            @view-details="handleViewDetails"
          />
        </div>
      </ScrollArea>
      
      <div class="border-t px-4 py-3">
        <Button
          variant="ghost"
          class="w-full"
          :as="Link"
          href="/dashboard/notifications"
        >
          View all notifications
        </Button>
      </div>
    </PopoverContent>
  </Popover>
  
  <!-- Notification Details Modal -->
  <Dialog v-model:open="modalOpen">
    <DialogContent data-testid="notification-modal">
      <DialogHeader>
        <DialogTitle>{{ selectedNotification?.title }}</DialogTitle>
      </DialogHeader>
      <div v-if="selectedNotification" class="space-y-4">
        <p>{{ selectedNotification.message }}</p>
        <div v-if="selectedNotification.context" class="rounded-lg bg-muted p-4">
          <pre class="text-xs">{{ JSON.stringify(selectedNotification.context, null, 2) }}</pre>
        </div>
        <div class="text-sm text-muted-foreground">
          <p>Type: {{ selectedNotification.type }}</p>
          <p>Severity: {{ selectedNotification.severity }}</p>
          <p>Created: {{ selectedNotification.created_at }}</p>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import ScrollArea from '@/components/ui/scroll-area/ScrollArea.vue';
import NotificationList from './NotificationList.vue';
import { useNotificationStore } from '@/stores/notification';
import type { Notification } from '@/types/notification';

const notificationStore = useNotificationStore()
const loading = ref(false)
const severityFilter = ref('')
const modalOpen = ref(false)
const selectedNotification = ref<Notification | null>(null)

// Fetch notifications when component mounts
onMounted(async () => {
  await notificationStore.fetchNotifications()
})

const notifications = computed(() => notificationStore.notifications)
const unreadCount = computed(() => notificationStore.unreadCount)

const filteredNotifications = computed(() => {
  if (!severityFilter.value) return notifications.value
  return notifications.value.filter(n => n.severity === severityFilter.value)
})

const handleMarkAsRead = async (id: number) => {
  await notificationStore.markAsRead(id)
}

const handleDismiss = async (id: number) => {
  await notificationStore.dismiss(id)
}

const handleViewDetails = (notification: Notification) => {
  selectedNotification.value = notification
  modalOpen.value = true
}

const markAllAsRead = async () => {
  loading.value = true
  await notificationStore.markAllAsRead()
  loading.value = false
}

const clearAll = async () => {
  loading.value = true
  await notificationStore.clearAll()
  loading.value = false
}
</script>