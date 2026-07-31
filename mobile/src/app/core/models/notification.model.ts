export type NotificationType =
  | 'expense_submitted'
  | 'expense_approved'
  | 'expense_rejected'
  | 'budget_alert'
  | 'team_invite'
  | 'savings_goal_reached';

export interface Notification {
  id: number;
  type: NotificationType;
  message: string;
  data?: Record<string, unknown>;
  isRead: boolean;
  createdAt: string;
}

export interface NotificationListResponse {
  items: Notification[];
  unreadCount: number;
}
