<template>
  <Popover>
    <PopoverTrigger as-child>
      <Button variant="ghost" size="icon" class="relative">
        <Bell class="h-5 w-5" />
        <span
          v-if="unreadCount > 0"
          class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs text-white"
        >
          {{ unreadCount > 99 ? '99+' : unreadCount }}
        </span>
      </Button>
    </PopoverTrigger>
    
    <PopoverContent class="w-96 p-0" align="end">
      <div class="flex items-center justify-between border-b px-4 py-3">
        <h3 class="font-semibold">Notifications</h3>
        
        <div class="flex items-center gap-1">
          <Button
            v-if="unreadCount > 0"
            variant="ghost"
            size="sm"
            @click="markAllAsRead"
            :disabled="loading"
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
      
      <ScrollArea class="h-[400px]">
        <div class="p-4">
          <NotificationList
            :notifications="notifications"
            @mark-as-read="handleMarkAsRead"
            @dismiss="handleDismiss"
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
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import ScrollArea from '@/components/ui/scroll-area/ScrollArea.vue';
import NotificationList from './NotificationList.vue';
import { useNotificationStore } from '@/stores/notification';

const notificationStore = useNotificationStore()
const loading = ref(false)

const notifications = computed(() => notificationStore.notifications)
const unreadCount = computed(() => notificationStore.unreadCount)

const handleMarkAsRead = async (id: number) => {
  await notificationStore.markAsRead(id)
}

const handleDismiss = async (id: number) => {
  await notificationStore.dismiss(id)
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