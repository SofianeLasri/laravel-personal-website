export interface Notification {
    id: number;
    user_id?: number;
    type: 'success' | 'error' | 'warning' | 'info';
    title: string;
    message: string;
    data?: Record<string, any>;
    source?: string;
    action_url?: string;
    action_label?: string;
    is_read: boolean;
    is_persistent: boolean;
    read_at?: string;
    expires_at?: string;
    created_at: string;
    updated_at: string;
}

export interface NotificationResponse {
    data: Notification[];
    meta?: {
        current_page: number;
        from: number;
        last_page: number;
        per_page: number;
        to: number;
        total: number;
    };
}

export interface CreateNotificationRequest {
    type: 'success' | 'error' | 'warning' | 'info';
    title: string;
    message: string;
    data?: Record<string, any>;
    source?: string;
    action_url?: string;
    action_label?: string;
    is_persistent?: boolean;
    expires_at?: string;
}
