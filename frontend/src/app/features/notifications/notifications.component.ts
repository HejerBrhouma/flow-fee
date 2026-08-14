import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import 'dayjs/locale/fr';
import { NotificationService } from '../../core/services/notification.service';
import { Notification, NotificationType } from '../../core/models/notification.model';

dayjs.extend(relativeTime);
dayjs.locale('fr');

@Component({
  selector: 'app-notifications',
  templateUrl: './notifications.component.html',
})
export class NotificationsComponent implements OnInit {
  notifications: Notification[] = [];
  unreadCount = 0;
  loading = true;
  error = false;

  readonly icons: Record<NotificationType, string> = {
    expense_submitted: '📤',
    expense_approved: '✅',
    expense_rejected: '❌',
    budget_alert: '⚠️',
    team_invite: '👥',
    savings_goal_reached: '🎯',
  };

  constructor(
    private notificationService: NotificationService,
    private router: Router,
  ) {}

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.loading = true;
    this.error = false;
    this.notificationService.getAll().subscribe({
      next: (res) => {
        this.notifications = res.items;
        this.unreadCount = res.unreadCount;
        this.loading = false;
      },
      error: () => {
        this.loading = false;
        this.error = true;
      },
    });
  }

  select(notification: Notification): void {
    if (!notification.isRead) {
      this.notificationService.markRead(notification.id).subscribe(() => {
        notification.isRead = true;
        this.unreadCount = Math.max(0, this.unreadCount - 1);
      });
    }

    this.navigateTo(notification);
  }

  markAllRead(): void {
    if (this.unreadCount === 0) return;

    this.notificationService.markAllRead().subscribe(() => {
      this.notifications.forEach(n => (n.isRead = true));
      this.unreadCount = 0;
    });
  }

  delete(notification: Notification, event: Event): void {
    event.stopPropagation();

    this.notificationService.delete(notification.id).subscribe(() => {
      this.notifications = this.notifications.filter(n => n.id !== notification.id);
      if (!notification.isRead) {
        this.unreadCount = Math.max(0, this.unreadCount - 1);
      }
    });
  }

  timeAgo(dateStr: string): string {
    return dayjs(dateStr).fromNow();
  }

  private navigateTo(notification: Notification): void {
    const data = notification.data as { expenseId?: number } | undefined;

    if (data?.expenseId) {
      this.router.navigate(['/expenses', data.expenseId]);
    } else if (notification.type === 'savings_goal_reached') {
      this.router.navigateByUrl('/savings-goals');
    } else if (notification.type === 'budget_alert') {
      this.router.navigateByUrl('/budgets');
    }
  }
}
