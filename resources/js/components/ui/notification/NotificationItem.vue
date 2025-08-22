<template>
  <div
    :class="cn(
      'relative flex w-full items-start gap-3 rounded-lg border p-4 shadow-sm transition-all',
      'hover:shadow-md',
      notificationVariants[notification.type],
      { 'opacity-60': notification.is_read }
    )"
  >
    <div class="flex-shrink-0">
      <component
        :is="iconComponent"
        :class="cn('h-5 w-5', iconColors[notification.type])"
      />
    </div>
    
    <div class="flex-1 space-y-1">
      <div class="flex items-start justify-between gap-2">
        <div>
          <p class="text-sm font-medium">{{ notification.title }}</p>
          <p class="text-sm text-muted-foreground">{{ notification.message }}</p>
        </div>
        
        <div class="flex items-center gap-1">
          <Button
            v-if="!notification.is_read"
            variant="ghost"
            size="sm"
            @click="markAsRead"
            :disabled="loading"
          >
            <Check class="h-4 w-4" />
          </Button>
          
          <Button
            v-if="!notification.is_persistent"
            variant="ghost"
            size="sm"
            @click="dismiss"
            :disabled="loading"
          >
            <X class="h-4 w-4" />
          </Button>
        </div>
      </div>
      
      <div v-if="notification.action_url" class="pt-2">
        <Button
          variant="outline"
          size="sm"
          :as="Link"
          :href="notification.action_url"
        >
          {{ notification.action_label || 'View' }}
        </Button>
      </div>
      
      <div class="flex items-center gap-2 text-xs text-muted-foreground">
        <span>{{ formatRelativeTime(notification.created_at) }}</span>
        <span v-if="notification.source" class="flex items-center gap-1">
          <span>•</span>
          <span>{{ notification.source }}</span>
        </span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { AlertTriangle, Check, CheckCircle, Info, X, XCircle } from 'lucide-vue-next';
import { formatRelativeTime } from '@/lib/date-utils';
import type { Notification } from '@/types/notification';

interface Props {
  notification: Notification
  loading?: boolean
}

const props = defineProps<Props>()
const emit = defineEmits<{
  'mark-as-read': [id: number]
  'dismiss': [id: number]
}>()

const notificationVariants = {
  success: 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950',
  error: 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950',
  warning: 'border-yellow-200 bg-yellow-50 dark:border-yellow-800 dark:bg-yellow-950',
  info: 'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950',
}

const iconColors = {
  success: 'text-green-600 dark:text-green-400',
  error: 'text-red-600 dark:text-red-400',
  warning: 'text-yellow-600 dark:text-yellow-400',
  info: 'text-blue-600 dark:text-blue-400',
}

const iconComponent = computed(() => {
  switch (props.notification.type) {
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
})

const markAsRead = () => {
  emit('mark-as-read', props.notification.id)
}

const dismiss = () => {
  emit('dismiss', props.notification.id)
}
</script>