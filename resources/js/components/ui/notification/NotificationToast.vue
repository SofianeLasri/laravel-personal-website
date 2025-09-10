<template>
  <Teleport to="body">
    <TransitionGroup
      name="toast"
      tag="div"
      class="fixed bottom-4 right-4 z-50 flex flex-col gap-2"
    >
      <div
        v-for="notification in toastNotifications"
        :key="notification.id"
        :class="cn(
          'flex w-full max-w-sm items-start gap-3 rounded-lg border p-4 shadow-lg',
          'bg-background',
          toastVariants[notification.type]
        )"
      >
        <div class="flex-shrink-0">
          <component
            :is="getIcon(notification.type)"
            :class="cn('h-5 w-5', iconColors[notification.type])"
          />
        </div>
        
        <div class="flex-1 space-y-1">
          <p class="text-sm font-medium">{{ notification.title }}</p>
          <p class="text-sm text-muted-foreground">{{ notification.message }}</p>
        </div>
        
        <Button
          variant="ghost"
          size="sm"
          @click="dismiss(notification.id)"
        >
          <X class="h-4 w-4" />
        </Button>
      </div>
    </TransitionGroup>
  </Teleport>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { AlertTriangle, CheckCircle, Info, X, XCircle } from 'lucide-vue-next';
import { useNotificationStore } from '@/stores/notification';

const notificationStore = useNotificationStore()

const toastNotifications = computed(() => notificationStore.toastNotifications)

const toastVariants = {
  success: 'border-green-200 dark:border-green-800',
  error: 'border-red-200 dark:border-red-800',
  warning: 'border-yellow-200 dark:border-yellow-800',
  info: 'border-blue-200 dark:border-blue-800',
}

const iconColors = {
  success: 'text-green-600 dark:text-green-400',
  error: 'text-red-600 dark:text-red-400',
  warning: 'text-yellow-600 dark:text-yellow-400',
  info: 'text-blue-600 dark:text-blue-400',
}

const getIcon = (type: string) => {
  switch (type) {
    case 'success':
      return CheckCircle
    case 'error':
      return XCircle
    case 'warning':
      return AlertTriangle
    case 'info':
    default:
      return Info
  }
}

const dismiss = (id: number) => {
  notificationStore.dismissToast(id)
}
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(100%);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(100%);
}

.toast-move {
  transition: transform 0.3s ease;
}
</style>