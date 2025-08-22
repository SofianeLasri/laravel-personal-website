<template>
  <div class="space-y-2">
    <div v-if="notifications.length === 0" class="text-center py-8 text-muted-foreground">
      <Bell class="h-12 w-12 mx-auto mb-3 opacity-30" />
      <p>No notifications</p>
    </div>
    
    <TransitionGroup
      v-else
      name="notification"
      tag="div"
      class="space-y-2"
    >
      <NotificationItem
        v-for="notification in notifications"
        :key="notification.id"
        :notification="notification"
        :loading="loadingIds.includes(notification.id)"
        @mark-as-read="handleMarkAsRead"
        @dismiss="handleDismiss"
      />
    </TransitionGroup>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { Bell } from 'lucide-vue-next';
import NotificationItem from './NotificationItem.vue';
import type { Notification } from '@/types/notification';

interface Props {
  notifications: Notification[]
}

const props = defineProps<Props>()
const emit = defineEmits<{
  'mark-as-read': [id: number]
  'dismiss': [id: number]
}>()

const loadingIds = ref<number[]>([])

const handleMarkAsRead = async (id: number) => {
  loadingIds.value.push(id)
  emit('mark-as-read', id)
  // Remove from loading after a delay (will be handled by parent)
  setTimeout(() => {
    loadingIds.value = loadingIds.value.filter(loadingId => loadingId !== id)
  }, 500)
}

const handleDismiss = async (id: number) => {
  loadingIds.value.push(id)
  emit('dismiss', id)
  // Remove from loading after a delay (will be handled by parent)
  setTimeout(() => {
    loadingIds.value = loadingIds.value.filter(loadingId => loadingId !== id)
  }, 500)
}
</script>

<style scoped>
.notification-enter-active,
.notification-leave-active {
  transition: all 0.3s ease;
}

.notification-enter-from {
  opacity: 0;
  transform: translateX(-30px);
}

.notification-leave-to {
  opacity: 0;
  transform: translateX(30px);
}

.notification-move {
  transition: transform 0.3s ease;
}
</style>